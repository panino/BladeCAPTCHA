# BladeCAPTCHA

BladeCAPTCHA is an accessible, self-hosted, privacy-friendly CAPTCHA system developed in PHP and JavaScript.  
It offers two usage modes:
1. **Automatic form integration** (automatically adds the hidden token).
2. **Manual verification** (developer manages the validation flow).

---

## Features

- **No external dependencies**: no third-party libraries or external services required.
- **Privacy-friendly**: does not track users or store personal data.
- **No cookies**: important for compliance with privacy regulations.
- **Accessible**: designed to be usable with screen readers.
- **Self-hosted**: full control over code and configuration.
- **Adaptive protection**: adjusts the challenge execution based on the user’s device capabilities and performance.

---

## Privacy

BladeCAPTCHA is designed to minimize data collection and support compliance with regulations such as GDPR (EU) and CCPA (California):

- No cookies or persistent local storage.
- Does not collect IP addresses or browser fingerprints.
- All processing is performed on your own server (self-hosted).

> Note: final compliance depends on your specific implementation.

---

## Project Structure

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
│   │   ├── manual-verification.html
│   │   └── dual-forms.html
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

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/panino/BladeCAPTCHA.git
   ```
2. Rename `config/config.sample.php` to `config/config.php` and edit values:
   ```bash
   mv config/config.sample.php config/config.php
   nano config/config.php
   ```
3. Ensure PHP 8.0+ and the following extensions are installed: `openssl`, `mbstring`, `json`.

---

## Usage

### Mode 1: Automatic form integration

Example: [`public/examples/form-integration.html`](public/examples/form-integration.html)

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'autoFormIntegration',
      // other parameters...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

### Mode 2: Manual handling

Example: [`public/examples/manual-verification.html`](public/examples/manual-verification.html)

```js
import { initCaptcha } from './js/captcha.js';

(async () => {
  try {
    await initCaptcha({
      mode: 'manualHandling',
      // other parameters...
    });
  } catch (err) {
    console.error(err || err.message);
  }
})();
```

---

## Execution Adjustment

BladeCAPTCHA dynamically optimizes the execution parameters of its proof-of-work challenge—such as the number of parallel workers and the size of computation batches—based on the device's hardware capabilities.

This optimization ensures that devices with lower processing power, such as mobile phones, can complete verification swiftly, while maintaining a high level of security against bots.

The entire calculation and adjustment process occurs locally within the browser. No hardware information or performance metrics are ever sent to external servers, ensuring complete user privacy.

---

## Requirements

- Browser with **Web Workers** and **JavaScript ES6+** support.
- Server running PHP 8.0+ with extensions: `openssl`, `mbstring`, `json`.

---

## 🌐 Documentation and Live Examples

- 📚 **Full Documentation (ES)**: [https://bladecaptcha.com.ar/documentacion.html](https://bladecaptcha.com.ar/documentacion.html)  
- 📚 **Full Documentation (EN)**: [https://bladecaptcha.com.ar/en/documentacion.html](https://bladecaptcha.com.ar/en/documentacion.html)  

**Interactive examples**:
- 🔹 **Automatic integration**: [form-integration.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/form-integration.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/form-integration-en.html)  
- 🔹 **Manual handling**: [manual-verification.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/manual-verification.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/manual-verification-en.html)  
- 🔹 **Automatic integration with multiple forms**: [dual-forms.html (ES)](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/dual-forms.html) | [EN](https://bladecaptcha.com.ar/BladeCAPTCHA/public/examples/dual-forms-en.html)

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Credits

The emoji font used in examples is **Twemoji**, courtesy of Twitter.  
The specific font comes from Mozilla's **twemoji-colr** project.  
Both are licensed under **CC-BY-4.0**.

- Twemoji: [https://github.com/twitter/twemoji](https://github.com/twitter/twemoji)  
- twemoji-colr: [https://github.com/mozilla/twemoji-colr](https://github.com/mozilla/twemoji-colr)
