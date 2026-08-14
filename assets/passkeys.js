(function () {
  const config = window.atshiftFreeformLoginPasskeys;

  if (!config) {
    return;
  }

  const supportsPasskeys = () => (
    window.PublicKeyCredential &&
    navigator.credentials &&
    typeof navigator.credentials.create === 'function'
  );

  const setStatus = (root, message) => {
    const status = root.querySelector('.atshift-freeform-login-passkey-status');

    if (status) {
      status.textContent = message || '';
    }
  };

  const request = async (path, options) => {
    const response = await fetch(config.restUrl + path, {
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      ...options
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.message || config.messages.failed);
    }

    return data;
  };

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

  const parseCreationOptions = (publicKey) => {
    if (PublicKeyCredential.parseCreationOptionsFromJSON) {
      return PublicKeyCredential.parseCreationOptionsFromJSON(publicKey);
    }

    return {
      ...publicKey,
      challenge: base64urlToBuffer(publicKey.challenge),
      user: {
        ...publicKey.user,
        id: base64urlToBuffer(publicKey.user.id)
      },
      excludeCredentials: (publicKey.excludeCredentials || []).map((credential) => ({
        ...credential,
        id: base64urlToBuffer(credential.id)
      }))
    };
  };

  const credentialToJSON = (credential) => {
    const response = {
      clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
      attestationObject: bufferToBase64url(credential.response.attestationObject)
    };

    if (typeof credential.response.getTransports === 'function') {
      response.transports = credential.response.getTransports();
    }

    return {
      id: credential.id,
      rawId: bufferToBase64url(credential.rawId),
      type: credential.type,
      authenticatorAttachment: credential.authenticatorAttachment || null,
      response
    };
  };

  const appendCredential = (root, credential) => {
    const list = root.querySelector('.atshift-freeform-login-passkey-list');
    const empty = root.querySelector('.atshift-freeform-login-passkey-empty');

    if (!list) {
      return;
    }

    if (empty) {
      empty.remove();
    }

    const item = document.createElement('li');
    item.dataset.credentialId = credential.credential_id;
    item.innerHTML = `
      <div class="atshift-freeform-login-passkey-details">
        <strong class="atshift-freeform-login-passkey-label"></strong>
        <div class="atshift-freeform-login-passkey-meta">
          <span class="description atshift-freeform-login-passkey-registered"></span>
          <span class="description atshift-freeform-login-passkey-last-used"></span>
        </div>
      </div>
      <button type="button" class="button-link-delete atshift-freeform-login-passkey-delete"></button>
    `;
    item.querySelector('.atshift-freeform-login-passkey-label').textContent = credential.label;
    item.querySelector('.atshift-freeform-login-passkey-registered').textContent = config.messages.registeredNow;
    item.querySelector('.atshift-freeform-login-passkey-last-used').textContent = config.messages.lastUsedNever;
    item.querySelector('button').textContent = config.messages.delete;
    item.querySelector('button').dataset.credentialId = credential.credential_id;
    list.appendChild(item);
  };

  const registerPasskey = async (root) => {
    if (!supportsPasskeys()) {
      throw new Error(config.messages.unsupported);
    }

    setStatus(root, config.messages.registering);

    const userId = Number(root.dataset.userId || config.currentUserId);
    const options = await request('registration/options', {
      method: 'POST',
      body: JSON.stringify({ userId })
    });
    const credential = await navigator.credentials.create({
      publicKey: parseCreationOptions(options.publicKey)
    });

    if (!credential) {
      throw new Error(config.messages.failed);
    }

    const label = window.prompt(config.messages.namePrompt, config.messages.defaultName) || config.messages.defaultName;
    const verified = await request('registration/verify', {
      method: 'POST',
      body: JSON.stringify({
        userId,
        requestId: options.requestId,
        label,
        credential: credentialToJSON(credential)
      })
    });

    appendCredential(root, verified.credential);
    setStatus(root, config.messages.registered);
  };

  const deletePasskey = async (root, button) => {
    const credentialId = button.dataset.credentialId;
    const userId = Number(root.dataset.userId || config.currentUserId);

    if (!window.confirm(config.messages.confirmDelete)) {
      return;
    }

    await request(`${credentialId}?userId=${encodeURIComponent(userId)}`, {
      method: 'DELETE'
    });

    const item = button.closest('li');
    if (item) {
      item.remove();
    }

    const list = root.querySelector('.atshift-freeform-login-passkey-list');
    if (list && !list.querySelector('li')) {
      const empty = document.createElement('p');
      empty.className = 'description atshift-freeform-login-passkey-empty';
      empty.textContent = config.messages.none;
      list.insertAdjacentElement('afterend', empty);
    }
    setStatus(root, config.messages.deleted);
  };

  document.addEventListener('click', async (event) => {
    const addButton = event.target.closest('.atshift-freeform-login-passkey-add');
    const deleteButton = event.target.closest('.atshift-freeform-login-passkey-delete');
    const root = event.target.closest('.atshift-freeform-login-passkeys');

    if (!root || (!addButton && !deleteButton)) {
      return;
    }

    event.preventDefault();

    const activeButton = addButton || deleteButton;
    activeButton.disabled = true;

    try {
      if (addButton) {
        await registerPasskey(root);
      } else {
        await deletePasskey(root, deleteButton);
      }
    } catch (error) {
      setStatus(root, error.message || config.messages.failed);
    } finally {
      activeButton.disabled = false;
    }
  });
}());
