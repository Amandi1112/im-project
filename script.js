document.addEventListener('DOMContentLoaded', function() {
    const addSafetyStockForm = document.getElementById('addSafetyStockForm');
    const checkSafetyStockForm = document.getElementById('checkSafetyStockForm');
    const resultDiv = document.getElementById('result');

    addSafetyStockForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const itemId = document.getElementById('itemId').value;
        const safetyStockQuantity = document.getElementById('safetyStockQuantity').value;

        fetch('addSafetyStock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `itemId=${itemId}&safetyStockQuantity=${safetyStockQuantity}`
            })
            .then(response => response.text())
            .then(message => {
                resultDiv.innerText = message;
            })
            .catch(error => console.error('Error:', error));
    });

    checkSafetyStockForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const checkItemId = document.getElementById('checkItemId').value;

        fetch('checkSafetyStock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `itemId=${checkItemId}`
            })
            .then(response => response.text())
            .then(message => {
                resultDiv.innerText = message;
            })
            .catch(error => console.error('Error:', error));
    });
});