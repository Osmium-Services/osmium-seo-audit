UPDATE {PREFIX}seo_audit_pages SET fetched = 1, status_code = :status, redirect_target = :redirect, fetch_error = :error,
    title = :title, description = :description, canonical = :canonical, robots = :robots, h1_count = :h1, heading_levels = :levels,
    word_count = :words, images_missing_alt = :alt, og_title = :ogTitle, og_image = :ogImage, has_viewport = :viewport,
    jsonld_invalid = :jsonld, body_hash = :hash, response_ms = :ms, bytes = :bytes, links = :links, lang = :lang, icons = :icons, images = :images, insecure = :insecure
WHERE id = :id
