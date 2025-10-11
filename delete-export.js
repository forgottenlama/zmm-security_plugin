// Event listener pre "Odstrániť" tlačidlá
document.querySelectorAll('.delete-grid').forEach(function(button) {
    button.addEventListener('click', function() {
        var gridKey = this.getAttribute('data-key');  // Získame kľúč Grid karty
        var confirmDelete = confirm('Naozaj chcete odstrániť túto kartu?');

        if (confirmDelete) {
            fetch(gridAuthData.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=delete_grid_card&nonce=' + gridAuthData.delete_nonce + '&grid_key=' + encodeURIComponent(gridKey)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.data); // Zobrazíme správu o úspechu
                    // Po odstránení karty, odstránime riadok z tabuľky
                    this.closest('tr').remove();
                } else {
                    alert('Chyba pri odstraňovaní karty: ' + data.data);
                }
            })
            .catch(error => {
                alert('Chyba pri komunikácii s serverom.');
                console.error(error);
            });
        }
    });
});

// Event listener pre "Exportovať" tlačidlá
document.querySelectorAll('.export-grid').forEach(function(button) {
    button.addEventListener('click', function() {
        var gridKey = this.getAttribute('data-key');  // Získame kľúč Grid karty
        var exportUrl = gridAuthData.ajaxurl + '?action=export_grid_to_pdf&grid_id=' + encodeURIComponent(gridKey);
        window.open(exportUrl, '_blank');
    });
});