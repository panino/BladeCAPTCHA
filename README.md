# BladeCAPTCHA

BladeCAPTCHA es un sistema de verificación tipo CAPTCHA accesible, auto-hosteado y respetuoso con la privacidad, desarrollado en PHP y JavaScript.  
Permite dos modos de uso:
1. **Integración automática con formularios** (agrega el token oculto automáticamente).
2. **Verificación manual** (el desarrollador gestiona el flujo de validación).

---

## Características

- **Sin dependencias externas**: no requiere librerías de terceros ni servicios externos.
- **Privacidad**: no rastrea al usuario ni guarda información personal.
- **Accesible**: diseñado para ser usable con lectores de pantalla.
- **Auto-hosteado**: control total sobre el código y la configuración.
- **Protección adaptativa**: ajusta la dificultad de los desafíos según el comportamiento del cliente.

---

## Estructura del proyecto

```
BladeCAPTCHA/
│
├── public/                   
│   ├── css/
│   │   └── captcha.css
│   ├── js/
│   │   ├── captcha.js
│   │   └── workers/
│   │       ├── pow-worker.js
│   │       └── benchmark-worker.js
│   ├── examples/
│   │   ├── form-integration.html
│   │   └── manual-verification.html
│   ├── php/
│   │   ├── captcha.php
│   │   ├── captcha-lib.php
│   │   └── procesar-formulario.php
│   └── index.html
│
├── config/                    
│   └── config.php
│
├── .gitignore
├── README.md
└── LICENSE
```

---

## Instalación

1. Clonar el repositorio:
   ```bash
   git clone https://github.com/usuario/bladecaptcha.git
   ```
2. Mover `config/config.php` fuera del directorio público de tu servidor.
3. Configurar los parámetros de `config.php` (dificultad, tiempo de vida de tokens, etc.).
4. Asegurarse de que PHP 8.0+ y las extensiones necesarias estén instaladas.

---

## Uso

### Modo 1: Integración automática con formularios
Ejemplo: [`public/examples/form-integration.html`](public/examples/form-integration.html)

Incluye `captcha.js` y llama a `initCaptcha({ mode: 'autoFormIntegration', ... })`.

### Modo 2: Manejo manual
Ejemplo: [`public/examples/manual-verification.html`](public/examples/manual-verification.html)

Permite controlar cuándo iniciar el desafío y qué hacer con la respuesta.

---

## Licencia
Este proyecto está licenciado bajo la licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más información.
