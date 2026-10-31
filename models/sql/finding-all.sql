SELECT check_key, severity, page_url, detail FROM {PREFIX}seo_audit_findings WHERE run_id = :run
ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), check_key, page_url
