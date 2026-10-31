UPDATE {PREFIX}seo_audit_runs SET status = 'failed', token = NULL, finished_at = NOW() WHERE status = 'running' AND started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
