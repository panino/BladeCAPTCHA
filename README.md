# BladeCAPTCHA

BladeCAPTCHA es un sistema de CAPTCHA accesible, auto-hosteado y orientado a la privacidad.  
Diseñado para funcionar sin depender de servicios externos y ofreciendo dos modos de integración:
- **Modo autónomo**: El cliente maneja el flujo y recibe callbacks tras validación exitosa o fallida.
- **Modo de integración con formularios**: El script agrega automáticamente un campo oculto con un token validado en el backend.

## Características
- 100% auto-hospedado
- Compatible con PHP y JavaScript puros
- Enfoque en accesibilidad y privacidad
- Dos modos de funcionamiento
- Desafío de prueba de trabajo (PoW) ajustable según rendimiento
- Validación sencilla de token en backend

## Requisitos
- PHP 8.1 o superior
- Navegador con soporte para Web Workers y SubtleCrypto

## Instalación
1. Clona este repositorio en tu servidor:
   ```bash
   git clone https://github.com/usuario/BladeCAPTCHA.git
   ```
2. Copia `config/config.sample.php` como `config/config.php` y ajusta la configuración.
3. Coloca el contenido del directorio `public/` en el directorio público de tu servidor.

## Estructura del proyecto
```
BladeCAPTCHA/
│
├── config/              # Archivos de configuración (fuera del public)
│   ├── config.sample.php
│   └── config.php       # Configuración real (no subir a git)
│
├── public/              # Archivos accesibles públicamente
│   ├── css/
│   ├── js/
│   │   └── workers/
│   ├── captcha.php      # Endpoint público del captcha
│   └── index.html       # Ejemplo de integración
│
├── src/                 # Código PHP principal
│   ├── captcha-lib.php
│   └── otros.php
│
└── README.md
```

## Uso
### Modo integración con formularios
Incluye el script JS y llama a `initCaptcha` con `mode: 'autoFormIntegration'`.  
El script añadirá automáticamente un campo oculto con un token válido antes de enviar el formulario.

### Modo autónomo
Llama a `initCaptcha` con `mode: 'manualHandling'` y gestiona la respuesta en tu callback.

## Licencia
Este proyecto está licenciado bajo la **MIT License**. Consulta el archivo LICENSE para más detalles.
