document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("generate_grid").addEventListener("click", function(){
        let secret = document.getElementById("grid_secret").value;
        if (!secret) {
            alert("Prosím, zadajte kľúč.");  // kontrola aby kľúč nebol prázdny
            return;
        }
        fetch(gridAuthData.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=save_and_generate_grid&nonce=" + gridAuthData.nonce + "&grid_secret=" + encodeURIComponent(secret) // pridanie nonce a kľúča do požiadavky
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Karta bola úspešne uložená.");
            } else {
                alert("Chyba: " + data.data);
            }
        });
    });
});
