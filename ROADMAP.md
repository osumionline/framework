Proximamente en Osumi Framework:

**9.4.0**

* `ORoute::view` Método para asignar directamente un Template a una ruta, sin tener que pasar por un componente. Sirve para mostrar datos estáticos.
* Nueva task `generateModelFrom --file` Método inverso a `generateModel`, sirve para generar las clases de modelo directamente a partir de un archivo JSON con la definición de una base de datos.
* Permitir método `run` en componentes que no son de ruta, si lo tiene ejecutarlo antes de hacer `render`. Serviría tanto para componentes de ruta (quitaría la ejecución de `OCore->run`) como componentes reutilizables. `render` tendría que aceptar `ORequest` y `ODTO`.

**9.3.0**

* Nuevo ORM.
* Nuevo sistema de acceso a base de datos y nuevo sistema de clases de modelos.
* Métodos estáticos en las clases de modelos para realizar consultas rápidas con métodos como `where`, `findOne`, `create` y `from`.
* Nuevo sistema de validación de clases de modelo y validación de datos al guardar.
* Sistema de cache de resultados para evitar consultas repetitivas.

**~~9.2.1~~**

* ~~Correcciones varias tras la actualización a `9.2.0`~~
* ~~Correcciones en `add modelComponent`~~
