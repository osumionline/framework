# Referencia de Configuración

Este documento proporciona una lista completa de todas las opciones de configuración disponibles en el framework. Cada opción se describe con su valor predeterminado y una breve explicación.

| Nombre de la Opción  | Valor Predeterminado | Descripción                                                                            |
| -------------------- | -------------------- | -------------------------------------------------------------------------------------- |
| `name`               | `Osumi`              | Nombre de la aplicación.                                                               |
| `environment`        | (cadena vacía)       | Nombre del entorno (p. ej., `development`, `production`).                              |
| `log.level`          | `DEBUG`              | Nivel de registro (p. ej., `DEBUG`, `INFO`, `WARN`, `ERROR`).                          |
| `log.max_file_size`  | `50`                 | Tamaño máximo (en MB) de los archivos de registro.                                     |
| `log.max_num_files`  | `3`                  | Número máximo de archivos de registro que se conservarán.                              |
| `use_session`        | `false`              | Si se utilizan sesiones.                                                               |
| `allow_cross_origin` | `true`               | Si se permite el uso compartido de recursos entre orígenes (CORS).                     |
| `db.driver`          | `mysql`              | El controlador de la base de datos (p. ej., `mysql`, `pgsql`).                         |
| `db.user`            | (cadena vacía)       | El nombre de usuario de la base de datos.                                              |
| `db.pass`            | (cadena vacía)       | La contraseña de la base de datos.                                                     |
| `db.host`            | (cadena vacía)       | El host de la base de datos.                                                           |
| `db.name`            | (cadena vacía)       | El nombre de la base de datos.                                                         |
| `db.charset`         | `utf8mb4`            | El conjunto de caracteres de la base de datos.                                         |
| `db.collate`         | `utf8mb4_unicode_ci` | La intercalación de la base de datos.                                                  |
| `urls.base`          | (cadena vacía)       | La URL base de la aplicación.                                                          |
| `cookie_prefix`      | (cadena vacía)       | Prefijo para cookies.                                                                  |
| `cookie_url`         | (cadena vacía)       | URL para cookies.                                                                      |
| `error_pages.403`    | `null`               | URL para la página de error 403.                                                       |
| `error_pages.404`    | `null`               | URL para la página de error 404.                                                       |
| `error_pages.500`    | `null`               | URL para la página de error 500.                                                       |
| `default_title`      | (cadena vacía)       | Título predeterminado para la aplicación.                                              |
| `admin_email`        | (cadena vacía)       | Dirección de correo electrónico del administrador.                                     |
| `mailing_from`       | (cadena vacía)       | Dirección de correo electrónico utilizada para enviar correos electrónicos.            |
| `lang`               | `es`                 | Idioma predeterminado para la aplicación.                                              |
| `css_list`           | `[]`                 | Lista de archivos CSS para incluir.                                                    |
| `js_list`            | `[]`                 | Lista de archivos JavaScript a incluir.                                                |
| `head_elements`      | `[]`                 | Lista de elementos HTML para inyectar en el <head> del documento (meta, link, script). |
| `libs`               | `[]`                 | Lista de bibliotecas a incluir.                                                        |
| `extras`             | `[]`                 | Opciones de configuración adicionales.                                                 |
