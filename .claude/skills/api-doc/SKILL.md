---
name: api-doc
description: Generate API documentation from Laravel routes, controllers, and resources
disable-model-invocation: true
---

Generate API documentation for the Laravel backend.

## Steps

1. Run `docker exec laravel_app php artisan route:list --json --path=api` to get all API routes
2. For each route, read the controller method and its Form Request validation rules
3. Read the corresponding Eloquent API Resource to determine the response shape
4. Output a structured Markdown document with:
   - Endpoint URL and HTTP method
   - Route name and middleware
   - Request parameters and validation rules (from Form Request)
   - Response shape (from API Resource fields)
   - Example response based on the Resource structure

## Output Format

Group endpoints by API version and resource (e.g., Blogs, Projects, Shares).

```markdown
## GET /api/v1/blogs

**Route name:** `api.v1.blogs.index`
**Middleware:** `api`

### Parameters
| Parameter | Type | Rules |
|-----------|------|-------|
| page | integer | optional |

### Response (BlogSummaryResource)
```json
{
  "id": 1,
  "title": "string",
  "slug": "string",
  ...
}
```
```
