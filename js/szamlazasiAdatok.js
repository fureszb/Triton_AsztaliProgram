document.addEventListener('DOMContentLoaded', function () {
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
            szamlazoNev.readOnly = true;
            szamlazoCim.readOnly = true;
            postaCim.readOnly = true;
        } else {
            szamlazoNev.value = '';
            szamlazoCim.value = '';
            postaCim.value = '';
            szamlazoNev.readOnly = false;
            szamlazoCim.readOnly = false;
            postaCim.readOnly = false;
        }
    }

    checkbox.addEventListener('change', updateBillingFields);
    szerzodoNeve.addEventListener('input', updateBillingFields);
    cime.addEventListener('input', updateBillingFields);
    updateBillingFields(); // Kezdeti állapot beállítása
});