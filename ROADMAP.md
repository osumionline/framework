Proximamente en Osumi Framework:

**9.9.0**

- Nuevo sistema de `middlewares`.
- Sustituyen a los filtros actuales.
- Permite encadenar filtros indicando el orden.
- Se puede indicar si se deben ejecutar antes o después de la ejecución del componente: `before` sirve para validar usuarios o datos que se reciben y `after` sirve para realizar operaciones sobre el resultado obtenido.

Por ejemplo:

```
ORoute::get('/get-book-cover', GetBookCoverComponent::class)
	->addMiddlewareBefore(CheckUserMiddleware::class)
	->addMiddlewareBefore(GetBookMiddleware::class)
	->addMiddlewareAfter(MinifyJSONMiddleware::class);
```

**~~9.8.0~~**

- ~~Cambios para dejar de usar `ext_css_list` y `ext_js_list`, unificarlos bajo `head_elements` dando así la posibilidad de crear elementos personalizados adicionales.~~

**~~9.7.0~~**

- ~~Nuevo método `setHttpStatus` para establecer manualmente el HTTP Status.~~

**~~9.6.0~~**

- ~~Nueva tarea `generateModelFromDB` para generar las clases de modelo directamente a partir de una conexión configurada.~~

**~~9.5.0~~**

- ~~Nuevo sistema de DTOs basado en decoradores.~~
- ~~Extiende la clase `ODTO` en lugar de implementar una interfaz.~~
- ~~Elimininación de getters/setters.~~
- ~~Elimininación de funciones `load` e `isValid`.~~

**~~9.4.0~~**

- ~~`ORoute::view` Método para asignar directamente un Template a una ruta, sin tener que pasar por un componente. Sirve para mostrar datos estáticos.~~
    - ~~`ORoute::view("/url", "ruta_al_archivo.html");`~~
    - ~~`ORoute::view("/url", "ruta_al_archivo.html", LayoutComponent::class);`~~
- ~~Nueva task `generateModelFrom --file` Método inverso a `generateModel`, sirve para generar las clases de modelo directamente a partir de un archivo JSON con la definición de una base de datos.~~
- ~~Permitir método `run` en componentes que no son de ruta, si lo tiene ejecutarlo antes de hacer `render`. Serviría tanto para componentes de ruta (quitaría la ejecución de `OCore->run`) como componentes reutilizables. `render` tendría que aceptar `ORequest` y `ODTO`.~~
- ~~Pipes en templates:~~
    - ~~`{{ variable | función }}`~~
    - ~~También para objetos `{{ objeto.propiedad }}`~~
    - ~~`{{ variable | date("d/m/Y") }}` fecha/string con marcara para date o null.~~
    - ~~`{{ variable | date }}` como el anterior pero con máscara por defecto (d/m/Y h:i:s).~~
    - ~~`{{ variable | number(2, ",", "") }}` int/float con marcara para `number_format` o null.~~
    - ~~`{{ variable | number }}` como el anterior pero con máscara por defecto (".", "")~~
    - ~~`{{ variable | string }}` string con urlencode o null.~~
    - ~~`{{ variable | bool }}` bool o null.~~

**~~9.3.0~~**

- ~~Nuevo ORM.~~
- ~~Nuevo sistema de acceso a base de datos y nuevo sistema de clases de modelos.~~
- ~~Métodos estáticos en las clases de modelos para realizar consultas rápidas con métodos como `where`, `findOne`, `create` y `from`.~~
- ~~Nuevo sistema de validación de clases de modelo y validación de datos al guardar.~~
- ~~Sistema de cache de resultados para evitar consultas repetitivas.~~

**~~9.2.1~~**

- ~~Correcciones varias tras la actualización a `9.2.0`~~
- ~~Correcciones en `add modelComponent`~~
