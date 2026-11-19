ALTER TABLE {PREFIX}seo_audit_pages
    ADD COLUMN body_simhash CHAR(16) NULL DEFAULT NULL;
