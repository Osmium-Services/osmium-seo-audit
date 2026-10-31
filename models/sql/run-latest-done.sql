SELECT * FROM {PREFIX}seo_audit_runs WHERE status = 'done' AND id < :before ORDER BY id DESC LIMIT 1
