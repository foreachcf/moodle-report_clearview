//main.init();

// var ctxT1 = document.getElementById('myChartTarget1');
//
// new Chart(ctxT1, {
//     type: 'bar',
//     data: {
//         labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
//         datasets: [{
//             label: '# of Votes',
//             data: [12, 19, 3, 5, 2, 3],
//             borderWidth: 1
//         }]
//     },
//     options: {
//         responsive: true,
//         scales: {
//             y: {
//                 beginAtZero: true
//             }
//         }
//     }
// });
//
// var ctxT2 = document.getElementById("myChartTarget2");
//
// new Chart(ctxT2, {
//     type: 'line',
//     data: {
//         labels: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
//         datasets: [{
//             data: [15339, 21345, 18483, 24003, 23489, 24092, 12034],
//             lineTension: 0,
//             backgroundColor: 'transparent',
//             borderColor: '#007bff',
//             borderWidth: 4,
//             pointBackgroundColor: '#007bff'
//         }]
//     },
//     options: {
//         responsive: true,
//         scales: {
//             yAxes: [{
//                 ticks: {
//                     beginAtZero: false
//                 }
//             }]
//         },
//         legend: {
//             display: false,
//         }
//     }
// });

const data1 = {
    labels: [
        stringtranslations.jscompletiongraphcomplete,
        stringtranslations.jscompletiongraphincomplete
    ],
    datasets: [{
        label: stringtranslations.jscompletiongraphinpercentage,
        data: [categorycompletion, 100 - categorycompletion],
        backgroundColor: [
            '#fbbd36',
            '#4c5b5c',
        ],
        hoverOffset: 4
    }]
};

var ctxT1 = document.getElementById('myChartTarget1').getContext('2d');

new Chart(ctxT1, {
    type: 'pie',
    data: data1,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: stringtranslations.jscompletiongraphtitle,
                padding: {
                    top: 10,
                    bottom: 30
                },
                font: {
                    size: 24
                },
            },
        }
    }
});

// var ctxT2 = document.getElementById("myChartTarget2");
//
// new Chart(ctxT2, {
//     type: 'bar',
//     data: {
//         labels: ["rdd-1001", "rdd-1500", "rdd-990"],
//         datasets: [{
//             label: 'Nb de requêtes',
//             data: [65, 95, 22],
//             borderWidth: 1,
//             backgroundColor: ["#fbbd36"],
//         }]
//     },
//     options: {
//         responsive: true,
//         scales: {
//             y: {
//                 beginAtZero: true
//             }
//         },
//         plugins: {
//             title: {
//                 display: true,
//                 text: 'Cours actifs - 24h',
//                 padding: {
//                     top: 10,
//                     bottom: 30
//                 },
//                 font: {
//                     size: 24
//                 },
//             },
//         }
//     }
// });
//
// var ctxT3 = document.getElementById("myChartTarget3");
//
// new Chart(ctxT3, {
//     type: 'line',
//     data: {
//         labels: ["Dimanche", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi"],
//         datasets: [{
//             label: 'Nb de requêtes',
//             data: [15339, 21345, 18483, 24003, 23489, 24092, 12034],
//             lineTension: 0,
//             backgroundColor: 'transparent',
//             borderColor: "#fbbd36",
//             borderWidth: 4,
//             pointBackgroundColor: "#4c5b5c"
//         }]
//     },
//     options: {
//         responsive: true,
//         scales: {
//             yAxes: [{
//                 ticks: {
//                     beginAtZero: false
//                 }
//             }]
//         },
//         plugins: {
//             legend: {
//                 display: false
//             },
//             title: {
//                 display: true,
//                 text: 'Nombre de requêtes par jour',
//                 padding: {
//                     top: 10,
//                     bottom: 30
//                 },
//                 font: {
//                     size: 24
//                 },
//             },
//         }
//     }
// });

if (stringtranslations.jscurrentlocale !== 'en') {
    const currentlocaleindex = "jsbaseurl" + stringtranslations.jscurrentlocale.toString();

    new DataTable('#data-table-1', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
    new DataTable('#configurable-reports-list', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
    new DataTable('#clearview-reports-list', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
} else {
    new DataTable('#data-table-1');
    new DataTable('#configurable-reports-list');
    new DataTable('#clearview-reports-list');
}
