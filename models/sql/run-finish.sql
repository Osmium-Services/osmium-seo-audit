UPDATE {PREFIX}seo_audit_runs SET status = :status, token = NULL, pages_checked = :pages, score = :score, finished_at = NOW() WHERE id = :id
