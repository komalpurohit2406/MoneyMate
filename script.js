
const searchInput = document.getElementById("transactionSearch");
const transactionRows = document.getElementById("transactionRows");

if (searchInput && transactionRows) {
    searchInput.addEventListener("input", function () {
        const query = this.value.toLowerCase().trim();

        transactionRows.querySelectorAll("tr").forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(query)
                ? ""
                : "none";
        });
    });
}
