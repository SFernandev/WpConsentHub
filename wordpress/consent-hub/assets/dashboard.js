/**
 * ConsentHub Dashboard
 * Renders chart and metrics using Chart.js
 */
(function() {
	'use strict';

	if ( typeof chDashboard === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		initChart();
	});

	function initChart() {
		var canvasEl = document.getElementById( 'consentChart' );
		if ( ! canvasEl ) {
			return;
		}

		var ctx = canvasEl.getContext( '2d' );
		var chartData = chDashboard.chart;

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: chartData.labels,
				datasets: [
					{
						label: 'Aceptados',
						data: chartData.accepted,
						borderColor: '#27ae60',
						backgroundColor: 'rgba(39, 174, 96, 0.1)',
						tension: 0.4,
						fill: true,
						pointRadius: 5,
						pointHoverRadius: 7,
						pointBackgroundColor: '#27ae60',
						pointBorderColor: '#fff',
						pointBorderWidth: 2
					},
					{
						label: 'Rechazados',
						data: chartData.rejected,
						borderColor: '#e74c3c',
						backgroundColor: 'rgba(231, 76, 60, 0.1)',
						tension: 0.4,
						fill: true,
						pointRadius: 5,
						pointHoverRadius: 7,
						pointBackgroundColor: '#e74c3c',
						pointBorderColor: '#fff',
						pointBorderWidth: 2
					},
					{
						label: 'Parciales',
						data: chartData.partial,
						borderColor: '#f39c12',
						backgroundColor: 'rgba(243, 156, 18, 0.1)',
						tension: 0.4,
						fill: true,
						pointRadius: 5,
						pointHoverRadius: 7,
						pointBackgroundColor: '#f39c12',
						pointBorderColor: '#fff',
						pointBorderWidth: 2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: true,
						position: 'top',
						labels: {
							font: {
								size: 12,
								weight: '500'
							},
							padding: 15,
							usePointStyle: true,
							pointStyle: 'circle'
						}
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: {
							size: 13,
							weight: 'bold'
						},
						bodyFont: {
							size: 12
						},
						displayColors: true,
						borderColor: '#ddd',
						borderWidth: 1
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							stepSize: 1,
							font: {
								size: 11
							},
							color: '#999'
						},
						grid: {
							color: '#f0f0f0',
							drawBorder: false
						}
					},
					x: {
						ticks: {
							font: {
								size: 11
							},
							color: '#999'
						},
						grid: {
							display: false
						}
					}
				}
			}
		});
	}
})();
