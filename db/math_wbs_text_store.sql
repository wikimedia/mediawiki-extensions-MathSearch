--
-- Used by MathSearch to keep track of StackExchange ids
--
CREATE TABLE /*_*/math_wbs_text_store
(
    -- QId of the item.
    math_local_qid int PRIMARY KEY,
    -- QId that links to the question (or post for comments)
    math_reply_to  int NULL,
    -- type of the text (question, answer, comment)
    math_post_type int NOT NULL,
    -- wikitext of the post (might potentially  be longer than 65535b)
    math_body      mediumblob,
    -- timestamp
    math_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    -- key
) /*$wgDBTableOptions*/;