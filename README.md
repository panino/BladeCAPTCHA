
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
- **Protección adaptativa**: ajusta la dificultad de los desafíos según el comportamiento y rendimiento del cliente.
- **Optimizado para móviles**: detecta la velocidad de resolución de un desafío de prueba y adapta la dificultad para no sobrecargar dispositivos más lentos.

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
2. Renombrar `config/config.sample.php` a `config/config.php` y editar los valores:
   ```bash
   mv config/config.sample.php config/config.php
   nano config/config.php
   ```
3. Asegurarse de que PHP 8.0+ y las siguientes extensiones estén instaladas: `openssl`, `mbstring` y `json`.

---

## Uso

### Modo 1: Integración automática con formularios

Ejemplo: [`public/examples/form-integration.html`](public/examples/form-integration.html)

Importa el módulo `captcha.js` y llama a `initCaptcha` con la configuración deseada, por ejemplo:

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'autoFormIntegration',
      // otros parámetros...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

### Modo 2: Manejo manual

Ejemplo: [`public/examples/manual-verification.html`](public/examples/manual-verification.html)

Permite controlar cuándo iniciar el desafío y qué hacer con la respuesta.

Importa el módulo `captcha.js` y llama a `initCaptcha` así:

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'manualHandling',
      // otros parámetros...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

---

## Detección de rendimiento y ajuste dinámico

BladeCAPTCHA incluye un sistema de autoevaluación que mide el tiempo que tarda el dispositivo en resolver un desafío de prueba.  
Con esta información, ajusta la dificultad de los desafíos posteriores para equilibrar la seguridad con la experiencia de usuario, especialmente en dispositivos móviles o de bajo rendimiento.  
Esto garantiza que la verificación siga siendo segura sin causar demoras excesivas.

---

## Requisitos

- Navegador con soporte para <code>Web Workers</code> y <code>JavaScript ES6+</code>.
- Servidor capaz de servir archivos estáticos y ejecutar código PHP 8.0+, con las siguientes extensiones instaladas: `openssl`, `mbstring` y `json`.

---

## Licencia

Este proyecto está licenciado bajo la licencia MIT. Consulta el archivo [LICENSE](LICENSE) para más información.

## Créditos

La fuente de emoji utilizada en los ejemplos es **Twemoji**, cortesía de Twitter.  
La fuente específica proviene del proyecto **twemoji-colr** de Mozilla.  
Ambos proyectos están bajo licencia **CC-BY-4.0**.

Enlaces de referencia:  
- Twemoji: [https://github.com/twitter/twemoji](https://github.com/twitter/twemoji)  
- twemoji-colr: [https://github.com/mozilla/twemoji-colr](https://github.com/mozilla/twemoji-colr)

