SELECT id, url FROM {PREFIX}seo_audit_pages WHERE run_id = :run AND fetched = 0 ORDER BY source = 'linked', id LIMIT :limit
