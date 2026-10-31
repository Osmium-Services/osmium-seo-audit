SELECT id FROM {PREFIX}seo_audit_runs WHERE status = 'running' AND token = :token AND started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1
