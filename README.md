# Clean up leftover multi-meta

There is a potential bug in the REST API (identified by @MiguelAxcarHM, and being discussed between @TimothyBJacobs and @kadamwhite [here in WordPress core Slack](https://wordpress.slack.com/archives/C02RQC26G/p1663892062273559)) where updates to meta values can fail unexpectedly because of unrelated other meta.

Our working theory is that this happens when a meta key is registered (using `register_meta` or `register_post_meta`) with `'single' => true`, but that meta key still has multiple rows in the database as you would expect if it was previously used as `'single' => false`. For example,

```
+---------+---------+---------------------------------------------+---------------+
| meta_id | post_id | meta_key                                    | meta_value    |
+---------+---------+---------------------------------------------+---------------+
|    8898 |    5804 | process_owner                               | 333           |
|   11870 |    5804 | process_owner                               | 333           |
|   13868 |    5804 | process_owner                               | 333           |
|   15748 |    5804 | process_owner                               | 333           |
+---------+---------+---------------------------------------------+---------------+
```

This plugin provides a CLI command which can be used to iterate through all posts of a post type, check for registered meta for that post, and identify whether any `single`-registered meta keys have duplicated rows in the postmeta table. The duplicated meta IDs are then cleaned out.

If a meta key has multiple rows for a post with _different_ values, we should leave those alone.

Example command:

```
wp clean-up-leftover-multi-meta --post-type=my-cpt-name --dry-run
```
