/* LOCAL TO PROD */
UPDATE wp_options SET option_value = replace(option_value, 'potiskarium.loc', 'potiskarium.cz')
WHERE option_name = 'home' OR option_name = 'siteurl';
UPDATE wp_posts SET guid = replace(guid, 'potiskarium.loc', 'potiskarium.cz');
UPDATE wp_posts SET post_content = replace(post_content, 'potiskarium.loc', 'potiskarium.cz');
UPDATE wp_postmeta SET meta_value = replace(meta_value, 'potiskarium.loc', 'potiskarium.cz');

/* PROD TO LOCAL */
UPDATE wp_options SET option_value = replace(option_value, 'potiskarium.cz', 'potiskarium.loc')
WHERE option_name = 'home' OR option_name = 'siteurl';
UPDATE wp_posts SET guid = replace(guid, 'potiskarium.cz', 'potiskarium.loc');
UPDATE wp_posts SET post_content = replace(post_content, 'potiskarium.cz', 'potiskarium.loc');
UPDATE wp_postmeta SET meta_value = replace(meta_value, 'potiskarium.cz', 'potiskarium.loc');
