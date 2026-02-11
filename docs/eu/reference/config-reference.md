# Configuration Reference

This document provides a comprehensive list of all configuration options available in the framework. Each option is described with its default value and a brief explanation.

| Option Name          | Default Value        | Description                                                 |
| -------------------- | -------------------- | ----------------------------------------------------------- |
| `name`               | `Osumi`              | The name of the application.                                |
| `environment`        | (empty string)       | The environment name (e.g., `development`, `production`).   |
| `log.level`          | `DEBUG`              | The logging level (e.g., `DEBUG`, `INFO`, `WARN`, `ERROR`). |
| `log.max_file_size`  | `50`                 | Maximum size (in MB) for log files.                         |
| `log.max_num_files`  | `3`                  | Maximum number of log files to keep.                        |
| `use_session`        | `false`              | Whether to use sessions.                                    |
| `allow_cross_origin` | `true`               | Whether to allow Cross-Origin Resource Sharing (CORS).      |
| `db.driver`          | `mysql`              | The database driver (e.g., `mysql`, `pgsql`).               |
| `db.user`            | (empty string)       | The database username.                                      |
| `db.pass`            | (empty string)       | The database password.                                      |
| `db.host`            | (empty string)       | The database host.                                          |
| `db.name`            | (empty string)       | The database name.                                          |
| `db.charset`         | `utf8mb4`            | The database character set.                                 |
| `db.collate`         | `utf8mb4_unicode_ci` | The database collation.                                     |
| `urls.base`          | (empty string)       | The base URL of the application.                            |
| `cookie_prefix`      | (empty string)       | Prefix for cookies.                                         |
| `cookie_url`         | (empty string)       | URL for cookies.                                            |
| `error_pages.403`    | `null`               | URL for the 403 error page.                                 |
| `error_pages.404`    | `null`               | URL for the 404 error page.                                 |
| `error_pages.500`    | `null`               | URL for the 500 error page.                                 |
| `default_title`      | (empty string)       | Default title for the application.                          |
| `admin_email`        | (empty string)       | Administrator email address.                                |
| `mailing_from`       | (empty string)       | Email address used for sending emails.                      |
| `lang`               | `es`                 | Default language for the application.                       |
| `css_list`           | `[]`                 | List of CSS files to include.                               |
| `ext_css_list`       | `[]`                 | List of external CSS files to include.                      |
| `js_list`            | `[]`                 | List of JavaScript files to include.                        |
| `ext_js_list`        | `[]`                 | List of external JavaScript files to include.               |
| `libs`               | `[]`                 | List of libraries to include.                               |
| `extras`             | `[]`                 | Additional configuration options.                           |
