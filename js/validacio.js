function showAlert(message, targetElement = null) {
    const alert = document.createElement('div');
    alert.className = 'alert triton-alert fade show';
    const labelText = targetElement?.labels?.[0]?.innerText || 'Mező'; // Hibakezelés
    alert.innerHTML = `A(z) ${labelText} kitöltése kötelező!`;
    alert.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-3"></i>
            <div>${message}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Hibaüzenet elhelyezése a mező alatt
    if (targetElement) {
        targetElement.closest('.form-group').appendChild(alert); // A form-group elemhez adjuk hozzá
    } else {
        document.getElementById('alertContainer').appendChild(alert); // Vagy a headerben lévő tárolóhoz
    }

    return alert;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^\+?[0-9\s]{8,14}$/;
    return re.test(phone);
}

// Céges adatok validációja
function validateCegAdatok() {
    let isValid = true;
    const cegInputs = document.querySelectorAll('#ceg_adatok input');

    cegInputs.forEach(input => {
        if (!input.value.trim()) {
            showAlert(`A(z) ${input.labels[0].innerText} mező kitöltése kötelező!`, input);
            isValid = false;
        }
    });

    return isValid;
}

// Fő validációs függvény
function validateForm(event) {
    event.preventDefault();
    document.querySelectorAll('.alert').forEach(alert => alert.remove());

    let isValid = true;
    const requiredFields = document.querySelectorAll('[required]');

    // Kötelező mezők ellenőrzése + fókuszálás
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showAlert(`A(z) ${field.labels[0].innerText} mező kitöltése kötelező!`, field);
            field.focus(); // Fókuszálás a hibás mezőre
            isValid = false;
        }
    });

    // Egyedi validációk
    const emailField = document.querySelector('[name="email"]');
    if (emailField && !validateEmail(emailField.value)) {
        showAlert('Érvénytelen e-mail cím formátum!', emailField);
        isValid = false;
    }

    const phoneFields = document.querySelectorAll('[name="telefon_szama"], [name="ertesitendo_szemely_telefon[]"]');
    phoneFields.forEach(field => {
        if (field.value && !validatePhone(field.value)) {
            showAlert('Érvénytelen telefonszám formátum! (Elfogadott formátum: +36 20 123 4567)', field);
            isValid = false;
        }
    });

    // Céges adatok validációja
    // validacio.js
    document.getElementById('ceges_szerzodes')?.addEventListener('change', function () {
        const cegAdatok = document.getElementById('ceg_adatok');
        const inputs = cegAdatok.querySelectorAll('input');

        if (this.value === 'igen') {
            cegAdatok.classList.remove('hidden');
            inputs.forEach(input => input.required = true); // Kötelezővé tesszük
        } else {
            cegAdatok.classList.add('hidden');
            inputs.forEach(input => input.required = false); // Kötelezőt eltávolítjuk
        }
    });

    // Ha minden valid, elküldjük az űrlapot
    if (isValid) {
        event.target.submit();
    }
}


// Számlázási adatok másolása
function initBillingCopy() {
    const checkbox = document.getElementById('sameAsContractor');
    const szerzodoNeve = document.querySelector('[name="szerzodo_neve"]');
    const cime = document.querySelector('[name="cime"]');
    const szamlazoNev = document.getElementById('szamlara_irando_nev');
    const szamlazoCim = document.getElementById('szamlara_irando_cim');
    const postaCim = document.getElementById('postazasi_cim');

    function updateBillingFields() {
        if (checkbox.checked) {
            szamlazoNev.value = szerzodoNeve.value;
            szamlazoCim.value = cime.value;
            postaCim.value = cime.value;
            [szamlazoNev, szamlazoCim, postaCim].forEach(field => field.readOnly = true);
        } else {
            [szamlazoNev, szamlazoCim, postaCim].forEach(field => {
                field.value = '';
                field.readOnly = false;
            });
        }
    }

    checkbox.addEventListener('change', updateBillingFields);
    szerzodoNeve.addEventListener('input', updateBillingFields);
    cime.addEventListener('input', updateBillingFields);
}

// Oldal betöltésekor futó inicializálások
document.addEventListener('DOMContentLoaded', () => {
    // Űrlap eseménykezelő
    document.querySelector('form')?.addEventListener('submit', validateForm);

    // Dinamikus elemek inicializálása
    initDynamicFields();
    initBillingCopy();

    // Céges adatok kezelése
    document.getElementById('ceges_szerzodes')?.addEventListener('change', function () {
        const cegAdatok = document.getElementById('ceg_adatok');
        this.value === 'igen'
            ? cegAdatok.classList.remove('hidden')
            : cegAdatok.classList.add('hidden');
    });
});