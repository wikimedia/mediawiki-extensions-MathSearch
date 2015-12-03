CREATE TABLE /*_*/math_mlp (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(10) unsigned NOT NULL,
  json_data blob,
  comment varchar(255) DEFAULT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fId varchar(50) DEFAULT NULL,
  rev_id int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mathsearch_mlp_revision_rev_id_fk` (rev_id),
  KEY `mathsearch_mlp_user_user_id_fk` (user_id),
  CONSTRAINT `mathsearch_mlp_user_user_id_fk` FOREIGN KEY (user_id) REFERENCES `user` (user_id)
) /*$wgDBTableOptions*/;