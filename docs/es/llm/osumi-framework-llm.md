# Osumi Framework – Contexto LLM (Todo en uno)

**Propósito**  
Este documento proporciona un contexto compacto, completo y autoritativo para que cualquier LLM comprenda, explique y genere correctamente código para **Osumi Framework**.

## 0. Identidad del framework

- **Nombre:** Osumi Framework
- **Versión:** 9.8.5
- **Lenguaje:** PHP
- **Versión mínima de PHP:** 8.3+
- **Tipado:** `declare(strict_types=1)` es obligatorio
- **Estilo:** explícito, predecible, sin magia oculta

## 1. Filosofía

Osumi Framework prioriza el código explícito, la predictibilidad, la separación clara de responsabilidades y la composición.

## 2. Ciclo de vida de una solicitud

```text
Solicitud del cliente
↓
Routing (ORoute)
↓
Filtros (0..n)
↓
Hidratación y validación DTO (opcional)
↓
Component::run()
↓
Renderizado de plantilla
↓
Layout (opcional)
↓
Respuesta HTTP
```

- Los filtros se ejecutan antes que los componentes.
- Los DTO solo se crean cuando se declaran como parámetro de `run()`.
- Las plantillas renderizan las propiedades públicas del componente.

## 3. Bloques principales

### 3.1 Routing (ORoute)

Las rutas asignan URL a componentes. Se admiten GET, POST, PUT y DELETE. Los prefijos y grupos pueden anidarse y las URL se normalizan.

### 3.2 Filtros

Los filtros gestionan autenticación/autorización, bloqueo de solicitudes y carga de contexto. Su salida está disponible mediante `ORequest->getFilter('Name')`.

### 3.3 DTO (ODTO)

- Los DTO extienden `ODTO`.
- Los campos utilizan `#[ODTOField]`.
- El framework instancia, rellena, valida e inyecta los DTO en `run()`.
- Los DTO se detectan por herencia de `ODTO`, no por namespace.
- Los DTO no deben contener lógica de negocio.

### 3.4 Componentes (OComponent)

**Propósito:** orquestar solicitud → servicios/modelos → preparar salida → renderizar.

- Las propiedades públicas tipificadas se exponen a las plantillas.
- `run()` es opcional.
- `run()` admite exactamente una de estas firmas:
  - `run(): void`
  - `run(ORequest $req): void`
  - `run(MyDTO $dto): void`, donde `MyDTO` extiende `ODTO`
- Los DTO se detectan por herencia de `ODTO`, no por namespace.
- No se admiten otros tipos de parámetros ni otras firmas.

Mantén los componentes ligeros; mueve la lógica de negocio a los Servicios.

### 3.5 Plantillas

Los archivos de plantilla pueden ser `.php`, `.html`, `.json` o `.xml`. Las plantillas estáticas usan `{{ variable }}` y pipes.

### 3.6 Layouts

Los layouts envuelven la salida renderizada de un componente de ruta con una estructura compartida.

### 3.7 Pipes

Las plantillas admiten pipes de estilo Angular:

- `date` → formato `Y-m-d H:i:s` a una máscara (predeterminado `d/m/Y H:i:s`)
- `number` → formato de estilo `number_format`
- `string` → `urlencode` con salida entre comillas
- `plain` → cadena entre comillas segura para JSON sin codificación URL
- `bool` → `true | false | null`

### 3.8 Servicios (OService)

Los servicios extienden `OService` y contienen lógica de negocio reutilizable.

## 4. ORM (OModel)

Los modelos usan atributos PHP. Todo modelo debe definir al menos un `#[OPK]`, exactamente un `#[OCreatedAt]` y exactamente un `#[OUpdatedAt]`.

## 5. CLI (OTask)

Las tareas extienden `OTask` y utilizan `public function run(array $options = []): void`.

## 6. Plugins

Los plugins se instalan mediante Composer bajo `Osumi\OsumiFramework\Plugins`.

## 7. Configuración

Los archivos JSON de configuración se encuentran en `src/Config/`.

## 8. Convenciones estrictas

- Siempre `declare(strict_types=1)`
- Clases: PascalCase
- Archivos: PascalCase.php
- Tablas: snake_case
- Propiedades/campos: snake_case
- Preferir propiedades tipificadas y tipos de retorno explícitos.

## 9. No asumir

No asumir DI automática en propiedades, carga automática de relaciones, pipelines de middleware, serialización oculta ni helpers no documentados.

## 10. Guía para LLM

Usa este documento como contexto autoritativo del framework. Prefiere código explícito y tipado y no inventes comportamiento no documentado.
