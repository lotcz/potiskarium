SELECT
    table_name                                      AS `Table`,
    table_schema                                    AS `Database`,
    ROUND(data_length / 1024 / 1024, 2)             AS `Data Size (MB)`,
    ROUND(index_length / 1024 / 1024, 2)            AS `Index Size (MB)`,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS `Total Size (MB)`,
    table_rows                                      AS `Estimated Rows`
FROM
    information_schema.tables
WHERE
    table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
ORDER BY
    (data_length + index_length) DESC;

TRUNCATE TABLE wp_woocommerce_sessions;
