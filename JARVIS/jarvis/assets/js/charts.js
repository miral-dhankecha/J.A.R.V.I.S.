// ============================================
// charts.js - Pie chart (success vs fail) and Bar chart (most used commands)
// Uses Chart.js (loaded via CDN in dashboard.php)
// ============================================

let pieChartInstance = null;
let barChartInstance = null;

document.getElementById("btn-charts").addEventListener("click", () => {
    fetch("api/history.php")
        .then(r => r.json())
        .then(data => {
            const rows = data.rows || [];

            // ----- Pie: success vs fail -----
            let successCount = 0, failCount = 0;
            rows.forEach(r => r.status === "success" ? successCount++ : failCount++);

            const pieCtx = document.getElementById("pieChart").getContext("2d");
            if (pieChartInstance) pieChartInstance.destroy();
            pieChartInstance = new Chart(pieCtx, {
                type: "pie",
                data: {
                    labels: ["Successful", "Failed"],
                    datasets: [{
                        data: [successCount, failCount],
                        backgroundColor: ["#1e90ff", "#ff9800"]
                    }]
                },
                options: { plugins: { title: { display: true, text: "Jarvis Success vs Failed Commands" } } }
            });

            // ----- Bar: most used commands -----
            const freq = {};
            rows.forEach(r => {
                const cmd = r.command_text.toLowerCase();
                freq[cmd] = (freq[cmd] || 0) + 1;
            });
            const sorted = Object.entries(freq).sort((a, b) => b[1] - a[1]).slice(0, 5);

            const barCtx = document.getElementById("barChart").getContext("2d");
            if (barChartInstance) barChartInstance.destroy();
            barChartInstance = new Chart(barCtx, {
                type: "bar",
                data: {
                    labels: sorted.map(x => x[0]),
                    datasets: [{
                        label: "Usage Count",
                        data: sorted.map(x => x[1]),
                        backgroundColor: "#3f51b5"
                    }]
                },
                options: {
                    plugins: { title: { display: true, text: "Most Used Jarvis Commands" } },
                    scales: { y: { beginAtZero: true } }
                }
            });

            // NOTE: this line was missing before, which is why the
            // Charts button did nothing - the data was fetched and the
            // charts were built, but the modal itself was never shown.
            if (typeof openModal === "function") {
                openModal("charts-modal");
            } else {
                document.getElementById("charts-modal").classList.remove("hidden");
            }
        });
});
