# Search Engine — Implementation Plan
## Laravel + Vue.js + MySQL (FULLTEXT)

---

## Phase 1: Project Setup & Database
**Goal:** Laravel project running, database tables created, basic structure ready

### 1.1 — Create Laravel Project
```
Create a new Laravel 11 project called "search-engine".
Install and configure:
- Vue.js 3 with Vite (using laravel-vue plugin)
- Laravel Breeze or manual Vue setup with Inertia.js
- Configure MySQL connection in .env
```

### 1.2 — Database Migrations
```
Create these migrations with strict types and indexes:

1. domains table:
   - id (bigIncrements)
   - name (string, 255) — domain name like "digikala.com"
   - base_url (string, 500) — full URL like "https://digikala.com"
   - robots_txt (text, nullable) — cached robots.txt content
   - crawl_delay_ms (integer, default: 1000)
   - status (enum: pending, active, paused, blocked)
   - max_depth (integer, default: 5)
   - pages_count (integer, default: 0)
   - last_crawled_at (timestamp, nullable)
   - timestamps

2. pages table:
   - id (bigIncrements)
   - domain_id (foreignId → domains, cascadeOnDelete)
   - url (string, 2048)
   - url_hash (string, 64, unique) — SHA-256 of URL for fast lookup
   - title (string, 500, nullable)
   - meta_description (text, nullable)
   - meta_keywords (text, nullable)
   - content_raw (longText, nullable) — raw HTML
   - content_text (longText, nullable) — cleaned text for search
   - content_hash (string, 64, nullable) — detect duplicate content
   - http_status (smallInteger, nullable)
   - content_type (string, 100, nullable)
   - language (string, 10, nullable)
   - word_count (integer, default: 0)
   - depth (integer, default: 0)
   - page_rank (float, default: 0)
   - status (enum: pending, indexed, error)
   - crawled_at (timestamp, nullable)
   - indexed_at (timestamp, nullable)
   - timestamps
   - FULLTEXT INDEX on (title, content_text)
   - INDEX on (domain_id, status)
   - INDEX on (url_hash)

3. crawl_queue table:
   - id (bigIncrements)
   - domain_id (foreignId → domains, cascadeOnDelete)
   - url (string, 2048)
   - url_hash (string, 64, unique)
   - priority (integer, default: 0)
   - depth (integer, default: 0)
   - status (enum: pending, processing, done, failed)
   - attempts (integer, default: 0)
   - max_attempts (integer, default: 3)
   - last_attempt_at (timestamp, nullable)
   - timestamps
   - INDEX on (status, priority)

4. links table:
   - id (bigIncrements)
   - source_page_id (foreignId → pages, cascadeOnDelete)
   - target_page_id (foreignId → pages, nullable, nullOnDelete)
   - target_url (string, 2048)
   - anchor_text (string, 500, nullable)
   - is_external (boolean, default: false)
   - created_at (timestamp)
   - INDEX on (source_page_id)
   - INDEX on (target_page_id)

5. crawl_logs table:
   - id (bigIncrements)
   - domain_id (foreignId → domains, cascadeOnDelete)
   - page_id (foreignId → pages, nullable, nullOnDelete)
   - url (string, 2048)
   - status_code (smallInteger, nullable)
   - response_time_ms (integer, nullable)
   - content_size_bytes (integer, nullable)
   - error_message (text, nullable)
   - crawled_at (timestamp)
   - INDEX on (domain_id, crawled_at)

6. search_logs table:
   - id (bigIncrements)
   - query (string, 500)
   - results_count (integer, default: 0)
   - response_time_ms (integer, nullable)
   - ip_address (string, 45, nullable)
   - user_agent (string, 500, nullable)
   - searched_at (timestamp)
   - INDEX on (searched_at)
```

### 1.3 — Models & Relationships
```
Create Eloquent models with relationships:

- Domain: hasMany(Page), hasMany(CrawlQueue), hasMany(CrawlLog)
- Page: belongsTo(Domain), hasMany(Link, 'source_page_id')
- CrawlQueue: belongsTo(Domain)
- Link: belongsTo(Page, 'source_page_id'), belongsTo(Page, 'target_page_id')
- CrawlLog: belongsTo(Domain), belongsTo(Page)
- SearchLog: standalone

Add proper $fillable, $casts, and scopes for each model.
Add a scope for Page::search($query) using FULLTEXT MATCH AGAINST.
```

### 1.4 — Test Phase 1
```
- php artisan migrate — all tables created
- Test creating a domain, page, and running a FULLTEXT search in Tinker
- Make sure relationships work
```

---

## Phase 2: Crawler (Spider)
**Goal:** Give it a URL, it crawls pages and stores them

### 2.1 — Robots.txt Parser
```
Create a service App\Services\RobotsTxtParser that:
- Fetches robots.txt from a domain
- Parses Allow/Disallow rules
- Checks if a URL is allowed to crawl
- Extracts Crawl-delay if specified
- Caches the result in the domains table
```

### 2.2 — Core Crawler Service
```
Create App\Services\CrawlerService that:
- Takes a URL, fetches it with Http (Guzzle)
- Sets a proper User-Agent header
- Handles redirects, timeouts (30s), and errors
- Returns response body, status code, headers, response time
- Respects crawl delay between requests to same domain
```

### 2.3 — HTML Parser Service
```
Create App\Services\HtmlParser that:
- Takes raw HTML
- Extracts: title, meta description, meta keywords
- Extracts clean text content (strip all tags)
- Extracts all links (href) — both absolute and relative
- Converts relative URLs to absolute
- Detects language if possible
- Counts words
```

### 2.4 — Crawl Manager
```
Create App\Services\CrawlManager that orchestrates everything:

addDomain($url):
  - Extract domain name from URL
  - Create Domain record
  - Fetch and store robots.txt
  - Add the URL to crawl_queue

processQueue():
  - Get next pending item from crawl_queue (ordered by priority)
  - Check robots.txt permission
  - Call CrawlerService to fetch the page
  - Call HtmlParser to extract content
  - Save to pages table (with url_hash for dedup)
  - Extract links → add new ones to crawl_queue (if same domain & depth < max_depth)
  - Update domain pages_count and last_crawled_at
  - Log to crawl_logs
  - Handle errors gracefully
```

### 2.5 — Artisan Commands
```
Create these commands:

php artisan crawl:add {url}
  - Adds a domain and its first URL to the queue

php artisan crawl:run {--limit=100}
  - Processes N items from the queue
  - Shows progress bar

php artisan crawl:status
  - Shows stats: domains count, pages crawled, queue size
```

### 2.6 — Laravel Queue Job
```
Create App\Jobs\CrawlPageJob that:
- Can be dispatched to Redis/database queue
- Processes one URL from crawl_queue
- Has retry logic (max 3 attempts)
- Has rate limiting per domain
```

### 2.7 — Test Phase 2
```
- php artisan crawl:add https://example.com
- php artisan crawl:run --limit=10
- Check database: domain created? pages stored? links found?
- php artisan crawl:status — shows correct stats
- Test with a Persian site
```

---

## Phase 3: Search Engine (MySQL FULLTEXT)
**Goal:** User types a query, gets relevant results

### 3.1 — Search Service
```
Create App\Services\SearchService with:

search($query, $filters = []):
  - Use FULLTEXT MATCH AGAINST in Boolean Mode
  - Support filters: domain, language, date range
  - Return results with relevance score
  - Paginate results (10 per page)
  - Log search to search_logs with response time

prepareQuery($query):
  - Clean and sanitize the query
  - Handle Persian text normalization (ی/ک normalization)
  - Handle quoted phrases for exact match
```

### 3.2 — Search API Endpoint
```
Create SearchController with:

GET /api/search?q={query}&page=1&domain=&lang=
  - Validates input
  - Calls SearchService
  - Returns JSON: results, total, page, time_taken
  - Each result: title, url, snippet (highlighted), domain, crawled_at

GET /api/search/suggest?q={query}
  - Returns top 5 suggestions from previous searches
  - Uses search_logs table
```

### 3.3 — Snippet Generator
```
Create App\Services\SnippetGenerator that:
  - Takes page content_text and search query
  - Finds the best matching section (around the keywords)
  - Returns ~200 char snippet with keywords highlighted
  - Highlights with <mark> tags
```

### 3.4 — Test Phase 3
```
- Crawl a few sites first (Phase 2)
- Call /api/search?q=test — get results
- Test Persian queries
- Test exact phrase with quotes
- Check search_logs is being populated
```

---

## Phase 4: Ranking Algorithm
**Goal:** Most relevant results appear first

### 4.1 — Scoring Service
```
Create App\Services\RankingService that calculates a combined score:

1. Text Relevance (60%):
   - FULLTEXT MATCH score (MySQL provides this)
   - Bonus if query appears in title
   - Bonus if query appears in meta_description

2. Freshness (15%):
   - More recent pages get higher score
   - Decay function: score = 1 / (1 + days_since_crawl / 30)

3. Page Quality (15%):
   - Word count (longer content = usually better, up to a point)
   - Has meta description = bonus
   - Has proper title = bonus

4. Link Popularity (10%):
   - Number of internal links pointing to this page
   - Simple PageRank: pages with more backlinks rank higher
```

### 4.2 — Test Phase 4
```
- Search for a term that exists in multiple pages
- Verify that pages with query in title rank higher
- Verify that newer pages rank higher than old ones
```

---

## Phase 5: Vue.js Frontend
**Goal:** Beautiful, fast search interface

### 5.1 — Search Page Layout
```
Create Vue components:

SearchPage.vue — main page:
  - Google-style centered search box
  - Logo/brand above search box
  - Search button and keyboard Enter support

ResultsPage.vue — results display:
  - Search box at top (smaller)
  - Results count and time taken
  - List of results, each showing:
    - Title (clickable, linked to original URL)
    - URL in green
    - Snippet with highlighted keywords
    - Domain name and crawl date
  - Pagination at bottom

Use Tailwind CSS for styling.
Support RTL layout for Persian content.
```

### 5.2 — Autocomplete/Suggestions
```
Add autocomplete to search box:
  - Debounced API call (300ms)
  - Shows suggestions dropdown
  - Keyboard navigation (up/down/enter)
  - Shows recent popular searches
```

### 5.3 — Filters & Advanced Search
```
Add filter sidebar or chips:
  - Filter by domain
  - Filter by language (fa/en)
  - Filter by date range
  - Sort by: relevance / date
```

### 5.4 — Test Phase 5
```
- Open the search page in browser
- Type a query — suggestions appear
- Press Enter — results show with highlights
- Click pagination — next page loads
- Test RTL layout with Persian queries
- Test on mobile viewport
```

---

## Phase 6: Admin Panel & Optimization
**Goal:** Manage crawling, monitor performance

### 6.1 — Admin Dashboard
```
Create admin panel (separate Vue layout) with:
  - Total pages crawled
  - Total domains
  - Queue status (pending/processing/failed)
  - Searches today
  - Crawl speed (pages/hour)
  - Charts: pages crawled per day, searches per day
```

### 6.2 — Domain Management
```
Admin CRUD for domains:
  - Add new domain to crawl
  - Pause/resume/block domain
  - View domain stats (pages, last crawl, errors)
  - Set crawl depth and delay per domain
  - Manual re-crawl trigger
```

### 6.3 — Performance Optimization
```
Optimize for production:
  - Add Redis caching for popular search queries
  - Add rate limiting on search API (60/min per IP)
  - Optimize MySQL FULLTEXT with proper indexes
  - Add database query caching
  - Schedule periodic re-crawl (Laravel Scheduler)
```

### 6.4 — Test Phase 6
```
- Admin dashboard shows correct stats
- Add a domain from admin, verify crawling starts
- Pause a domain, verify crawling stops
- Test rate limiting: hit API 61 times, get 429
- Verify cache works: second search is faster
```

---

## Git Workflow

For each phase, create a branch:

```bash
git checkout -b phase-1/setup-database
# ... work ...
git add . && git commit -m "Phase 1: Project setup and database migrations"
git push origin phase-1/setup-database
git checkout main && git merge phase-1/setup-database
```

---

## Claude Code Prompts

Copy these directly into Claude Code for each phase:

### Phase 1 Prompt:
```
I'm building a search engine with Laravel 11 + Vue.js + MySQL.
Read the project plan in search-engine-plan.md.
Start with Phase 1:
1. Create the Laravel project
2. Create all 6 database migrations with the exact schema from the plan
3. Create all Eloquent models with relationships, $fillable, $casts
4. Add a search scope on Page model using FULLTEXT MATCH AGAINST
5. Run migrations and test in tinker
```

### Phase 2 Prompt:
```
Continue with Phase 2 of the search engine project.
Read search-engine-plan.md for full details.
Build the crawler system:
1. RobotsTxtParser service
2. CrawlerService (HTTP fetcher)
3. HtmlParser (extract content and links from HTML)
4. CrawlManager (orchestrator)
5. Artisan commands: crawl:add, crawl:run, crawl:status
6. CrawlPageJob for queue processing
Test by crawling https://example.com
```

### Phase 3 Prompt:
```
Continue with Phase 3 of the search engine project.
Read search-engine-plan.md for full details.
Build the search system:
1. SearchService with FULLTEXT MATCH AGAINST Boolean Mode
2. Persian text normalization (ی/ک)
3. SearchController API endpoints
4. SnippetGenerator with keyword highlighting
Test the search API with both English and Persian queries.
```

### Phase 4 Prompt:
```
Continue with Phase 4 of the search engine project.
Read search-engine-plan.md for full details.
Build the ranking system:
1. RankingService with combined scoring
2. Text relevance (60%) + Freshness (15%) + Quality (15%) + Links (10%)
3. Integrate with SearchService
Test that title matches rank higher than body-only matches.
```

### Phase 5 Prompt:
```
Continue with Phase 5 of the search engine project.
Read search-engine-plan.md for full details.
Build the Vue.js frontend:
1. SearchPage with centered search box (Google-style)
2. ResultsPage with highlighted snippets
3. Autocomplete with debounced API calls
4. Filters (domain, language, date)
5. RTL support for Persian
6. Tailwind CSS styling
7. Responsive design
```

### Phase 6 Prompt:
```
Continue with Phase 6 of the search engine project.
Read search-engine-plan.md for full details.
Build admin panel and optimize:
1. Admin dashboard with stats and charts
2. Domain CRUD management
3. Redis caching for popular searches
4. Rate limiting on search API
5. Laravel Scheduler for periodic re-crawl
```
