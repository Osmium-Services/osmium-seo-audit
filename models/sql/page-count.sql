SELECT COUNT(*) AS total, COALESCE(SUM(fetched), 0) AS done FROM {PREFIX}seo_audit_pages WHERE run_id = :run
