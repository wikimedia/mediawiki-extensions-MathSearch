CREATE TABLE /*_*/math_review_list (
  revision_id INT(11)     NOT NULL,
  anchor      VARCHAR(50) NOT NULL,
  priority    TINYINT(4)  NOT NULL,
  UNIQUE KEY ix_math_rev_list (revision_id, anchor)
) /*$wgDBTableOptions*/;