document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.uj-ertesitendo');
    const addButton = document.querySelector('.add-ertesitendo');
    let index = 1;

    addButton.addEventListener('click', function () {
        const newItem = document.createElement('div');
        newItem.className = 'ertesitendo-item mb-4';
        newItem.innerHTML = `
            <div class="g-3">
                <div class="form-group">
                    <input type="text" name="ertesitendo_szemely_neve[]" 
                           class="form-control modern-input" 
                           placeholder="Név">
                </div>
                <div class="form-group">
                    <input type="text" name="ertesitendo_szemely_telefon[]" 
                           class="form-control modern-input" 
                           placeholder="Telefonszám">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-ertesitendo w-100">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newItem);
        index++;
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-ertesitendo')) {
            e.target.closest('.ertesitendo-item').remove();
        }
    });
});

/*
// Dinamikus mezők kezelése
function initDynamicFields() {
    // Értesítendő személyek hozzáadása
    document.querySelector('.add-ertesitendo')?.addEventListener('click', () => {
        const newItem = document.createElement('div');
        newItem.className = 'ertesitendo-item mb-4';
        newItem.innerHTML = `
            <div class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="ertesitendo_szemely_neve[]" 
                           class="form-control modern-input" 
                           placeholder="Név">
                </div>
                <div class="col-md-5">
                    <input type="text" name="ertesitendo_szemely_telefon[]" 
                           class="form-control modern-input" 
                           placeholder="Telefonszám">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-ertesitendo w-100">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        document.querySelector('.uj-ertesitendo').appendChild(newItem);
    });

    // Értesítendő személyek törlése
    document.addEventListener('click', (e) => {
        if (e.target.closest('.remove-ertesitendo')) {
            e.target.closest('.ertesitendo-item').remove();
        }
    });
}*/
