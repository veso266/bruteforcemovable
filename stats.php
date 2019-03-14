<?php
if (isset($_REQUEST['getstats'])) {
	$dbCon = new \PDO("mysql:dbname=bruteforcemovable;host=127.0.0.1", 'bruteforcemovable', 'liK0sDLA'); 
	$statement = $dbCon->prepare('SELECT DATE_FORMAT(time_started,"%Y-%m-%d %H:00") as x, count(*) as y FROM seedqueue where movable is not null and movable != \'\' and time_started > (NOW() - INTERVAL 1 MONTH) GROUP BY year(time_started), month(time_started), day( time_started ), hour( time_started ) order by time_started asc');
	$result = $statement->execute();
	$retData = $statement->fetchAll(\PDO::FETCH_ASSOC);
	$statement2 = $dbCon->prepare('SELECT DATE_FORMAT(time_started + INTERVAL 1 DAY,"%Y-%m-%d") as x, count(*) as y FROM seedqueue where movable is not null and movable != \'\' and time_started > (NOW() - INTERVAL 1 MONTH - INTERVAL 1 DAY) GROUP BY year(time_started), month(time_started), day( time_started ) order by time_started asc');
	$result2 = $statement2->execute();
	$retData2 = $statement2->fetchAll(\PDO::FETCH_ASSOC);
	$statement3 = $dbCon->prepare('SELECT DATE_FORMAT(time_started + INTERVAL 1 DAY,"%Y-%m-%d") as x, count(*) as y FROM seedqueue where ((movable is not null and movable != \'\') or state = -1) and time_started > (NOW() - INTERVAL 1 MONTH - INTERVAL 1 DAY) GROUP BY year(time_started), month(time_started), day( time_started ) order by time_started asc');
	$result3 = $statement3->execute();
	$retData3 = $statement3->fetchAll(\PDO::FETCH_ASSOC);
	echo json_encode(array(
		array(
			"label" => "Successfull mines per hour",
			"data" => $retData,
			"yAxisID" => 'y-axis-1',
			"backgroundColor" => "rgba(255,0,0,.75)"
		),
		array(
			"label" => "Successfull mines per day",
			"data" => $retData2,
			"yAxisID" => 'y-axis-2',
			"backgroundColor" => "rgba(0,255,0,1)"
		),
		array(
			"label" => "Total mines per day",
			"data" => $retData3,
			"yAxisID" => 'y-axis-2',
			"backgroundColor" => "rgba(255,0,255,.5)"
		)
	));
	die;
}
?><html>
<head>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box }
	</style>
</head>
<body>
<canvas id="line-chart" width="800" height="450"></canvas>
<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://momentjs.com/downloads/moment.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
<script>
jQuery(function () {
	jQuery.ajax({
		dataType: 'JSON',
		url: 'stats.php?getstats',
		success: function (data) {
			new Chart(document.getElementById("line-chart"), {
	  type: 'line',
		data: {
			datasets: data
		},
	  
		
		options: {
					responsive: true,
					hoverMode: 'nearest',
					stacked: false,
					title: {
						display: true,
						text: 'Mining Stats - (Axis left = Hour | Axis right = Day)'
					},
					tooltips: {
			 mode: 'nearest', intersect: false
        },
					elements: { point: { radius: 0 }, line: {tension:0} },
					scales: {
						yAxes: [{
							type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
							display: true,
							position: 'left',
							id: 'y-axis-1',
						}, {
							type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
							display: true,
							position: 'right',
							id: 'y-axis-2',

							// grid line settings
							gridLines: {
								drawOnChartArea: false, // only want the grid lines for one axis to show up
							},
						}],
						xAxes: [{
							type: 'time',
							time: {
								displayFormats: {
									quarter: 'MMM YYYY'
								}
							}
						}]
					}
				}
		
	});
		}
	});
	
});

</script>
</body>
</html>
