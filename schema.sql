SET NAMES utf8;
SET CHARACTER SET utf8;

drop table if exists ttrss_related_articles;

CREATE TABLE `ttrss_related_articles` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `ref_id` int(11) NOT NULL,
 `title` text NOT NULL,
 `content` longtext NOT NULL,
 PRIMARY KEY (`id`),
 KEY `ref_id` (`ref_id`),
 FULLTEXT KEY `title` (`title`,`content`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8
