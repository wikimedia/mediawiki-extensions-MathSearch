INSERT INTO mathpage9 (page_id)
(SELECT `mathindex_page_id` from (
SELECT `mathindex_page_id` ,count(`mathindex_anchor`)  as cnt FROM `mathindex` group by mathindex_page_id ) t
WHERE
t.cnt > 9)