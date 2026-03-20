/**
 * ConsentHub Dashboard
 * Donut chart + Line chart (with period selector) + Paginated logs
 */
(function() {
	'use strict';

	if ( typeof chDashboard === 'undefined' ) return;

	var lineChart = null;

	document.addEventListener( 'DOMContentLoaded', function() {
		initDonutChart();
		initLineChart( chDashboard.chart );
		initPeriodSelector();
		initLogsPagination();
		initExportCsv();
	});

	/* ═══ DONUT CHART ═══ */

	function initDonutChart() {
		var el = document.getElementById( 'consentDonut' );
		if ( ! el ) return;

		var totals = chDashboard.totals;
		var total  = chDashboard.total_logs;
		var hasData = total > 0;

		var centerTextPlugin = {
			id: 'centerText',
			beforeDraw: function( chart ) {
				var w   = chart.width;
				var h   = chart.height;
				var ctx = chart.ctx;
				ctx.save();

				var fontSize = Math.min( w, h ) / 5;
				ctx.font = 'bold ' + fontSize + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillStyle = '#1a1a2e';
				ctx.fillText( total.toString(), w / 2, h / 2 - 8 );

				ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
				ctx.fillStyle = '#6b7280';
				ctx.fillText( 'Total', w / 2, h / 2 + fontSize / 2 + 4 );

				ctx.restore();
			}
		};

		new Chart( el.getContext( '2d' ), {
			type: 'doughnut',
			data: {
				labels: [ 'Aceptados', 'Rechazados', 'Parciales' ],
				datasets: [{
					data: hasData
						? [ totals.accepted, totals.rejected, totals.partial ]
						: [ 1 ],
					backgroundColor: hasData
						? [ '#22c55e', '#ef4444', '#3b82f6' ]
						: [ '#e5e7eb' ],
					borderWidth: 0,
					spacing: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				cutout: '70%',
				plugins: {
					legend: { display: false },
					tooltip: { enabled: hasData }
				}
			},
			plugins: [ centerTextPlugin ]
		});
	}

	/* ═══ LINE CHART ═══ */

	function initLineChart( data ) {
		var el = document.getElementById( 'consentChart' );
		if ( ! el ) return;

		if ( lineChart ) {
			lineChart.destroy();
			lineChart = null;
		}

		lineChart = new Chart( el.getContext( '2d' ), {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					makeDataset( 'Aceptados', data.accepted, '#22c55e', 'rgba(34, 197, 94, 0.08)' ),
					makeDataset( 'Rechazados', data.rejected, '#ef4444', 'rgba(239, 68, 68, 0.08)' ),
					makeDataset( 'Parciales', data.partial, '#3b82f6', 'rgba(59, 130, 246, 0.08)' )
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
							font: { size: 12, weight: '500' },
							padding: 16,
							usePointStyle: true,
							pointStyle: 'circle'
						}
					},
					tooltip: {
						backgroundColor: 'rgba(0, 0, 0, 0.8)',
						padding: 12,
						titleFont: { size: 13, weight: 'bold' },
						bodyFont: { size: 12 },
						displayColors: true,
						borderColor: '#374151',
						borderWidth: 1
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: { stepSize: 1, font: { size: 11 }, color: '#9ca3af' },
						grid: { color: '#f3f4f6', drawBorder: false }
					},
					x: {
						ticks: { font: { size: 11 }, color: '#9ca3af', maxRotation: 45 },
						grid: { display: false }
					}
				}
			}
		});
	}

	function makeDataset( label, data, color, bg ) {
		return {
			label: label,
			data: data,
			borderColor: color,
			backgroundColor: bg,
			tension: 0.4,
			fill: true,
			borderWidth: 2,
			pointRadius: 3,
			pointHoverRadius: 5,
			pointBackgroundColor: color,
			pointBorderColor: '#fff',
			pointBorderWidth: 2
		};
	}

	/* ═══ PERIOD SELECTOR ═══ */

	function initPeriodSelector() {
		var buttons = document.querySelectorAll( '.ch-period-btn' );
		if ( ! buttons.length ) return;

		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[i].addEventListener( 'click', onPeriodClick );
		}
	}

	function onPeriodClick( e ) {
		var btn  = e.currentTarget;
		var days = btn.getAttribute( 'data-days' );

		// Update active state
		var siblings = btn.parentNode.querySelectorAll( '.ch-period-btn' );
		for ( var i = 0; i < siblings.length; i++ ) siblings[i].classList.remove( 'active' );
		btn.classList.add( 'active' );

		// Fetch new data
		var url = chDashboard.ajax_url + '?action=ch_chart_data&nonce=' + chDashboard.nonce + '&days=' + days;
		fetch( url, { credentials: 'same-origin' } )
			.then( function( r ) { return r.json(); } )
			.then( function( resp ) {
				if ( resp.success && resp.data ) {
					initLineChart( resp.data );
				}
			});
	}

	/* ═══ CSV EXPORT ═══ */

	function initExportCsv() {
		var btn = document.getElementById( 'ch-export-csv' );
		if ( ! btn ) return;

		btn.addEventListener( 'click', function() {
			window.location.href = chDashboard.ajax_url
				+ '?action=ch_export_csv&nonce=' + chDashboard.nonce;
		});
	}

	/* ═══ LOGS PAGINATION ═══ */

	var currentPage      = 1;
	var currentPerPage   = 10;
	var currentType      = '';
	var currentRegion    = '';
	var currentDateFrom  = '';
	var currentDateTo    = '';

	function initLogsPagination() {
		var select = document.getElementById( 'ch-logs-per-page' );
		if ( ! select ) return;

		select.addEventListener( 'change', function() {
			currentPerPage = parseInt( this.value, 10 );
			currentPage = 1;
			fetchLogs();
		});

		// Event delegation on the container
		var paginationContainer = document.getElementById( 'ch-pagination-buttons' );
		if ( paginationContainer ) {
			paginationContainer.addEventListener( 'click', function( e ) {
				var btn = e.target.closest( '.ch-page-btn' );
				if ( ! btn ) return;
				var page = parseInt( btn.getAttribute( 'data-page' ), 10 );
				if ( page && page !== currentPage ) {
					currentPage = page;
					fetchLogs();
				}
			});
		}

		// Filter: apply button
		var btnApply = document.getElementById( 'ch-filter-apply' );
		if ( btnApply ) {
			btnApply.addEventListener( 'click', function() {
				currentType     = document.getElementById( 'ch-filter-type' ).value;
				currentRegion   = document.getElementById( 'ch-filter-region' ).value;
				currentDateFrom = document.getElementById( 'ch-filter-from' ).value;
				currentDateTo   = document.getElementById( 'ch-filter-to' ).value;
				currentPage = 1;
				fetchLogs();
			});
		}

		// Filter: clear button
		var btnClear = document.getElementById( 'ch-filter-clear' );
		if ( btnClear ) {
			btnClear.addEventListener( 'click', function() {
				currentType     = '';
				currentRegion   = '';
				currentDateFrom = '';
				currentDateTo   = '';
				document.getElementById( 'ch-filter-type' ).value   = '';
				document.getElementById( 'ch-filter-region' ).value = '';
				document.getElementById( 'ch-filter-from' ).value   = '';
				document.getElementById( 'ch-filter-to' ).value     = '';
				currentPage = 1;
				fetchLogs();
			});
		}

		// Render initial pagination from data PHP already passed via wp_localize_script
		var totalLogs = parseInt( chDashboard.total_logs, 10 ) || 0;
		if ( totalLogs > 0 ) {
			renderPagination({
				total:    totalLogs,
				pages:    Math.ceil( totalLogs / currentPerPage ),
				page:     1,
				per_page: currentPerPage
			});
		}
	}

	function fetchLogs() {
		var url = chDashboard.ajax_url
			+ '?action=ch_logs_page&nonce=' + chDashboard.nonce
			+ '&per_page=' + currentPerPage
			+ '&paged=' + currentPage;

		if ( currentType )     url += '&consent_type=' + encodeURIComponent( currentType );
		if ( currentRegion )   url += '&geo_region='   + encodeURIComponent( currentRegion );
		if ( currentDateFrom ) url += '&date_from='    + encodeURIComponent( currentDateFrom );
		if ( currentDateTo )   url += '&date_to='      + encodeURIComponent( currentDateTo );

		fetch( url, { credentials: 'same-origin' } )
			.then( function( r ) { return r.json(); } )
			.then( function( resp ) {
				if ( resp.success && resp.data ) {
					renderLogs( resp.data );
					renderPagination( resp.data );
				}
			});
	}

	function renderLogs( data ) {
		var tbody = document.getElementById( 'ch-logs-tbody' );
		if ( ! tbody ) return;

		if ( ! data.rows.length ) {
			tbody.innerHTML = '<tr><td colspan="5">Sin datos</td></tr>';
			return;
		}

		var html = '';
		for ( var i = 0; i < data.rows.length; i++ ) {
			var r = data.rows[i];
			html += '<tr>'
				+ '<td><code>' + escHtml( r.id ) + '</code></td>'
				+ '<td class="ch-ip-cell">' + renderMaskedIp( r.ip ) + '</td>'
				+ '<td><span class="ch-badge ch-badge-' + escHtml( r.type ) + '">' + escHtml( r.label ) + '</span></td>'
				+ '<td>' + escHtml( r.cats ) + '</td>'
				+ '<td>' + escHtml( r.region ) + '</td>'
				+ '<td>' + escHtml( r.date ) + '</td>'
				+ '</tr>';
		}
		tbody.innerHTML = html;
	}

	function renderPagination( data ) {
		var info = document.getElementById( 'ch-pagination-info' );
		var btns = document.getElementById( 'ch-pagination-buttons' );
		if ( ! info || ! btns ) return;

		var start = ( data.page - 1 ) * data.per_page + 1;
		var end   = Math.min( data.page * data.per_page, data.total );
		info.textContent = start + '–' + end + ' de ' + data.total + ' registros';

		var html = '';
		if ( data.page > 1 ) {
			html += '<button type="button" class="ch-page-btn" data-page="' + ( data.page - 1 ) + '">&laquo; Anterior</button>';
		}

		// Show page numbers (max 5 around current)
		var from = Math.max( 1, data.page - 2 );
		var to   = Math.min( data.pages, data.page + 2 );
		for ( var p = from; p <= to; p++ ) {
			html += '<button type="button" class="ch-page-btn' + ( p === data.page ? ' active' : '' ) + '" data-page="' + p + '">' + p + '</button>';
		}

		if ( data.page < data.pages ) {
			html += '<button type="button" class="ch-page-btn" data-page="' + ( data.page + 1 ) + '">Siguiente &raquo;</button>';
		}

		btns.innerHTML = html;
	}

	function renderMaskedIp( ip ) {
		if ( ! ip || ip === '—' ) return escHtml( '—' );
		var parts = ip.split( '.' );
		if ( parts.length < 4 ) return escHtml( ip );
		return escHtml( parts[0] + '.' + parts[1] + '.' + parts[2] + '.' )
			+ '<span class="ch-ip-blur">' + escHtml( parts[3] ) + '</span>';
	}

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

})();
