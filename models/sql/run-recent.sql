SELECT r.*, (SELECT COUNT(*) FROM {PREFIX}seo_audit_findings f WHERE f.run_id = r.id) AS finding_count,
    (SELECT COUNT(*) FROM {PREFIX}seo_audit_findings f WHERE f.run_id = r.id AND f.severity = 'critical') AS critical_count,
    (SELECT COUNT(*) FROM {PREFIX}seo_audit_findings f WHERE f.run_id = r.id AND f.severity = 'high') AS high_count
FROM {PREFIX}seo_audit_runs r ORDER BY r.id DESC LIMIT :limit
