(function () {
  const config = window.atshiftFreeformLoginPasskeyLogin;

  if (!config) {
    return;
  }

  const base64urlToBuffer = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=');
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }

    return bytes.buffer;
  };

  const bufferToBase64url = (buffer) => {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  };

  const parseRequestOptions = (publicKey) => {
    if (PublicKeyCredential.parseRequestOptionsFromJSON) {
      return PublicKeyCredential.parseRequestOptionsFromJSON(publicKey);
    }

    return {
      ...publicKey,
      challenge: base64urlToBuffer(publicKey.challenge),
      allowCredentials: (publicKey.allowCredentials || []).map((credential) => ({
        ...credential,
        id: base64urlToBuffer(credential.id)
      }))
    };
  };

  const credentialToJSON = (credential) => ({
    id: credential.id,
    rawId: bufferToBase64url(credential.rawId),
    type: credential.type,
    authenticatorAttachment: credential.authenticatorAttachment || null,
    response: {
      clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
      authenticatorData: bufferToBase64url(credential.response.authenticatorData),
      signature: bufferToBase64url(credential.response.signature),
      userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null
    }
  });

  const request = async (path, body) => {
    const response = await fetch(config.restUrl + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.message || config.messages.failed);
    }

    return data;
  };

  const setStatus = (root, message) => {
    const status = root.querySelector('.atshift-freeform-login-passkey-login-status');

    if (status) {
      status.textContent = message || '';
    }
  };

  document.querySelectorAll('.atshift-freeform-login-passkey-login-screen').forEach((root) => {
    const form = root.closest('form');
    const separator = root.nextElementSibling;

    if (!form || !separator || !separator.classList.contains('atshift-freeform-login-passkey-separator')) {
      return;
    }

    const firstField = form.firstElementChild;
    form.insertBefore(root, firstField);
    form.insertBefore(separator, firstField);
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.atshift-freeform-login-passkey-login-button');

    if (!button) {
      return;
    }

    event.preventDefault();

    const root = button.closest('.atshift-freeform-login-passkey-auth');

    if (!root) {
      return;
    }

    if (!window.PublicKeyCredential || !navigator.credentials || typeof navigator.credentials.get !== 'function') {
      setStatus(root, config.messages.unsupported);
      return;
    }

    button.disabled = true;
    setStatus(root, config.messages.authenticating);

    try {
      const form = root.closest('form') || root.parentElement.querySelector('form');
      const rememberField = form ? form.querySelector('input[name="rememberme"]') : null;
      let remember = Boolean(rememberField && rememberField.checked);

      if (root.dataset.remember === 'true') {
        remember = true;
      } else if (root.dataset.remember === 'false') {
        remember = false;
      }

      const options = await request('options', {
        redirect: root.dataset.redirect || '',
        remember
      });
      const credential = await navigator.credentials.get({
        publicKey: parseRequestOptions(options.publicKey)
      });

      if (!credential) {
        throw new Error(config.messages.failed);
      }

      const verified = await request('verify', {
        requestId: options.requestId,
        credential: credentialToJSON(credential)
      });

      window.location.assign(verified.redirect || window.location.href);
    } catch (error) {
      setStatus(root, error.message || config.messages.failed);
      button.disabled = false;
    }
  });
}());
