# BladeCAPTCHA

BladeCAPTCHA es un sistema de verificación tipo CAPTCHA accesible, auto-hosteado y respetuoso con la privacidad, desarrollado en PHP y JavaScript.  
Permite dos modos de uso:
1. **Integración automática con formularios** (agrega el token oculto automáticamente).
2. **Verificación manual** (el desarrollador gestiona el flujo de validación).

---

## Características

- **Sin dependencias externas**: no requiere librerías de terceros ni servicios externos.
- **Privacidad**: no rastrea al usuario ni guarda información personal.
- **No utiliza cookies**: importante para cumplir con regulaciones de privacidad.
- **Accesible**: diseñado para ser usable con lectores de pantalla.
- **Auto-hosteado**: control total sobre el código y la configuración.
- **Protección adaptativa**: ajusta la dificultad de los desafíos según el comportamiento del cliente.

---

## Privacidad

BladeCaptcha está diseñado para minimizar la recolección de datos y facilitar el cumplimiento de regulaciones como el RGPD (UE) y CCPA (California):

- No utiliza cookies ni almacenamiento local persistente.
- No recopila direcciones IP ni huellas del navegador.
- Todo el procesamiento se realiza en su propio servidor (self-hosted).

> Nota: la conformidad final dependerá de su implementación específica.

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
   git clone https://github.com/panino/BladeCAPTCHA.git
   ```
2. Copiar `config/config.sample.php` a `config/config.php` y editar los valores:
   ```bash
   cp config/config.sample.php config/config.php
   nano config/config.php
   ```
3. Asegurarse de que PHP 8.0+ y las siguientes extensiones estén instaladas: `openssl`, `mbstring` y `json`.

---

## Uso

### Modo 1: Integración automática con formularios
Ejemplo: [`public/examples/form-integration.html`](public/examples/form-integration.html)

Incluye `captcha.js` y llama a `initCaptcha({ mode: 'autoFormIntegration', ... })`.

### Modo 2: Manejo manual
Ejemplo: [`public/examples/manual-verification.html`](public/examples/manual-verification.html)

Permite controlar cuándo iniciar el desafío y qué hacer con la respuesta.

Incluye `captcha.js` y llama a `initCaptcha({ mode: 'manualHandling', ... })`.

---

## Licencia
Este proyecto está licenciado bajo la licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más información.
