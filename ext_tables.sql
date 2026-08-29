CREATE TABLE tx_fastblog_domain_model_blogpost (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT 0 NOT NULL,
    tstamp int(11) DEFAULT 0 NOT NULL,
    crdate int(11) DEFAULT 0 NOT NULL,
    cruser_id int(11) DEFAULT 0 NOT NULL,
    deleted smallint(5) unsigned DEFAULT 0 NOT NULL,
    hidden smallint(5) unsigned DEFAULT 0 NOT NULL,
    sys_language_uid int(11) DEFAULT 0 NOT NULL,
    l10n_parent int(11) DEFAULT 0 NOT NULL,
    l10n_diffsource mediumblob,

    title varchar(255) DEFAULT '' NOT NULL,
    slug varchar(255) DEFAULT '' NOT NULL,
    pub_date int(11) DEFAULT 0 NOT NULL,
    description varchar(512) DEFAULT '' NOT NULL,
    meta_description varchar(255) DEFAULT '' NOT NULL,
    focus_keywords varchar(512) DEFAULT '' NOT NULL,
    author varchar(255) DEFAULT '' NOT NULL,
    bodytext mediumtext,
    content_html mediumtext,
    source_file varchar(1024) DEFAULT '' NOT NULL,
    translation_key varchar(64) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY slug (slug),
    KEY source_file (source_file(255)),
    KEY pub_date (pub_date)
);
