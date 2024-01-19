<?php

	ini_set('display_errors', '1'); // Debugging
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);

	// Connection to the database.
	$conn = new mysqli("","webUserAccess","", "candidatesDatabase"); // Connection to RDS Database
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}	

	$resultsTableData = array();

?>

<html>
	<head>
		<title>Electoral Roll Database</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<!-- Bootstrap -->
		<link href="https://cdn.jsdelivr.net/npm/fastbootstrap@2.2.0/dist/css/fastbootstrap.min.css" rel="stylesheet" integrity="sha256-V6lu+OdYNKTKTsVFBuQsyIlDiRWiOmtC8VQ8Lzdm2i4=" crossorigin="anonymous">
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
		<link rel="stylesheet" href="css/style.css">
	</head>
	<body>
		<h1><i class="bi bi-database-fill"></i> Electoral Roll Database</h1>

		<!-- Tablist -->
		<ul class="nav nav-tabs" role="tablist">
			<li class="nav-item" role="presentation">
				<a class="nav-link active" id="tab-0" data-bs-toggle="tab" href="#tabpanel-0" role="tab" aria-controls="tabpanel-0" aria-selected="true">Data Overview</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-1" data-bs-toggle="tab" href="#tabpanel-1" role="tab" aria-controls="tabpanel-1" aria-selected="true">FPTP</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-2" data-bs-toggle="tab" href="#tabpanel-2" role="tab" aria-controls="tabpanel-2" aria-selected="true">SPR</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-3" data-bs-toggle="tab" href="#tabpanel-3" role="tab" aria-controls="tabpanel-3" aria-selected="true">SPR 5%</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-4" data-bs-toggle="tab" href="#tabpanel-4" role="tab" aria-controls="tabpanel-4" aria-selected="true">SPR (By County)</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-5" data-bs-toggle="tab" href="#tabpanel-5" role="tab" aria-controls="tabpanel-5" aria-selected="true">SPR (By Region)</a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="tab-6" data-bs-toggle="tab" href="#tabpanel-6" role="tab" aria-controls="tabpanel-6" aria-selected="true">SPR (By Country)</a>
			</li>
		</ul>
		<!-- Tab Content -->
		<div class="tab-content pt-5" id="tab-content">
			
			<div class="tab-pane" id="tabpanel-1" role="tabpanel" aria-labelledby="tab-1">
				<h2>First Past The Post</h2>
				<table class="table">
					<thead>
						<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
						<th scope="col">Party with most seats.</th>
						<th scope="col">Different from actual winner.</th>
						</tr>
					</thead>
					<tbody>
						<?php

						$statement = "
						WITH RankedVotes AS (
							SELECT party_ID, constituency_ID, votes, RANK() OVER (PARTITION BY constituency_ID ORDER BY votes DESC) AS vote_rank
							FROM votes
						),
						WinningParties AS (
							SELECT party_ID, constituency_ID, votes
							FROM RankedVotes
							WHERE vote_rank = 1
						),
						PartyTotalVotes AS (
							SELECT party_ID, SUM(votes) AS total_votes
							FROM votes
							GROUP BY party_ID
						),
						PartySeatRank AS (
							SELECT
								p.party_Name,
								COUNT(DISTINCT wp.constituency_ID) AS seats_won,
								RANK() OVER (ORDER BY COUNT(DISTINCT wp.constituency_ID) DESC) AS seat_rank
							FROM WinningParties wp
							JOIN parties p ON wp.party_ID = p.party_ID
							GROUP BY p.party_Name
						)
						SELECT 
							p.party_Name, 
							COUNT(DISTINCT wp.constituency_ID) AS seats_won,
							ROUND((COUNT(DISTINCT wp.constituency_ID) * 100.0 / (SELECT COUNT(DISTINCT constituency_ID) FROM WinningParties)), 2) AS percentage_of_seats,
							COALESCE(ptv.total_votes, 0) AS total_votes,
							ROUND((COALESCE(ptv.total_votes, 0) * 100.0 / (SELECT SUM(votes) FROM votes)), 2) AS percentage_of_votes,
							ROUND((COUNT(DISTINCT wp.constituency_ID) * 100.0 / (SELECT COUNT(DISTINCT constituency_ID) FROM WinningParties)) - (COALESCE(ptv.total_votes, 0) * 100.0 / (SELECT SUM(votes) FROM votes)), 2) AS percentage_difference,
							CASE WHEN p.party_Name = 'Conservatives' AND seat_rank = 1 THEN 'Yes' ELSE 'No' END AS different_from_winner,
							(SELECT party_Name FROM PartySeatRank WHERE seat_rank = 1) AS party_with_highest_seats
						FROM WinningParties wp
						JOIN parties p ON wp.party_ID = p.party_ID
						LEFT JOIN PartyTotalVotes ptv ON p.party_ID = ptv.party_ID
						LEFT JOIN PartySeatRank ON p.party_Name = 'Conservatives'
						GROUP BY p.party_Name, ptv.total_votes
						ORDER BY seats_won DESC;
									
						";

						$result = $conn->query($statement);

						if ($result) {
							$row = $result->fetch_assoc(); // Fetch only the first row
						
							if ($row) {
								$resultsTableData[] = array(
									"year" => 2019,
									"system_Name" => "First Past The Post", 
									"party" => $row["party_Name"],
									"number_of_seats" => $row["seats_won"],
									"percent_of_seats" => $row["percentage_of_seats"],
									"percent_of_pop_votes" => $row["percentage_of_votes"],
									"diff_between" => $row["percentage_difference"],
									"party_with_most" => $row["party_with_highest_seats"],
									"diff_from_winner" => $row["different_from_winner"]
								);
								echo '  <tr>';
								echo '    <td>' . $row["party_Name"] . '</td>';
								echo '    <td>' . $row["seats_won"] . '</td>';
								echo '    <td>' . $row["percentage_of_seats"] . '</td>';
								echo '    <td>' . $row["percentage_of_votes"] . '</td>';
								echo '    <td>' . $row["percentage_difference"] . '</td>';
								echo '    <td>' . $row["party_with_highest_seats"] . '</td>';
								echo '    <td>' . $row["different_from_winner"] . '</td>';
								echo '  </tr>';
							}
							
							while ($row = $result->fetch_assoc()) {
								echo '  <tr>';
								echo '    <td>' . $row["party_Name"] . '</td>';
								echo '    <td>' . $row["seats_won"] . '</td>';
								echo '    <td>' . $row["percentage_of_seats"] . '</td>';
								echo '    <td>' . $row["percentage_of_votes"] . '</td>';
								echo '    <td>' . $row["percentage_difference"] . '</td>';
								echo '    <td>' . $row["party_with_highest_seats"] . '</td>';
								echo '    <td>' . $row["different_from_winner"] . '</td>';
								echo '  </tr>';
							}

							$result->close();
						} else {
							echo "Error executing query: " . $conn->error;
						}

						

						?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane" id="tabpanel-2" role="tabpanel" aria-labelledby="tab-2">
				<h2>Simple Proportional Representation </h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
						<th scope="col">Party with most seats.</th>
						<th scope="col">Different from actual winner.</th>
					</tr>
					</thead>
					<tbody>
					<?php
					$statement =" 
					SELECT
						p.party_Name,
						ROUND((SUM(v.votes) / (SELECT SUM(votes) FROM votes)) * 100, 2) AS percentage_of_popular_votes,
						ROUND(SUM(v.votes) / (SELECT SUM(votes) FROM votes) * c.total_seats) AS seats_won,
						ROUND(SUM(v.votes) / (SELECT SUM(votes) FROM votes) * 100, 2) AS percentage_of_seats,
						ROUND((SUM(v.votes) / (SELECT SUM(votes) FROM votes) * c.total_seats / c.total_seats * 100) -
							(SUM(v.votes) / (SELECT SUM(votes) FROM votes) * 100), 2) AS difference_between,
						(SELECT p1.party_Name 
						FROM parties p1 
						JOIN votes v1 ON p1.party_ID = v1.party_ID
						GROUP BY v1.party_ID
						ORDER BY ROUND(SUM(v1.votes) / (SELECT SUM(votes) FROM votes) * c.total_seats) DESC
						LIMIT 1) AS party_with_most_seats,
						CASE WHEN 
							(SELECT p1.party_Name 
							FROM parties p1 
							JOIN votes v1 ON p1.party_ID = v1.party_ID
							GROUP BY v1.party_ID
							ORDER BY ROUND(SUM(v1.votes) / (SELECT SUM(votes) FROM votes) * c.total_seats) DESC
							LIMIT 1) = 'Conservative'
						THEN 'No' ELSE 'Yes' END AS difference_from_real_winner
					FROM
						votes v
					JOIN
						parties p ON v.party_ID = p.party_ID
					CROSS JOIN
						(SELECT COUNT(*) AS total_seats FROM constituencies) c
					GROUP BY
						v.party_ID
					HAVING
						seats_won > 0
					ORDER BY
						seats_won DESC;
					";

					$result = $conn->query($statement);

					if ($result) {
						$row = $result->fetch_assoc(); // Fetch only the first row
						if ($row) {
							$resultsTableData[] = array(
								"year" => 2019,
								"system_Name" => "Simple Proportional Representation", 
								"party" => $row["party_Name"],
								"number_of_seats" => $row["seats_won"],
								"percent_of_seats" => $row["percentage_of_seats"],
								"percent_of_pop_votes" => $row["percentage_of_popular_votes"],
								"diff_between" => $row["difference_between"],
								"party_with_most" => $row["party_with_most_seats"],
								"diff_from_winner" => $row["difference_from_real_winner"]
							);
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["seats_won"] . '</td>';
							echo '    <td>' . $row["percentage_of_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_popular_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '    <td>' . $row["party_with_most_seats"] . '</td>';
							echo '    <td>' . $row["difference_from_real_winner"] . '</td>';
							echo '  </tr>';
						}
						

						while ($row = $result->fetch_assoc()) {
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["seats_won"] . '</td>';
							echo '    <td>' . $row["percentage_of_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_popular_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '    <td>' . $row["party_with_most_seats"] . '</td>';
							echo '    <td>' . $row["difference_from_real_winner"] . '</td>';
							echo '  </tr>';
						}

						$result->close();
					} else {
						echo "Error executing query: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane" id="tabpanel-3" role="tabpanel" aria-labelledby="tab-3">
				<h2>Simple Proportional Representation (5% Threshold)</h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
						<th scope="col">Party with most seats.</th>
						<th scope="col">Different from actual winner.</th>
					</tr>
					</thead>
					<tbody>
					<?php
					$statement =" 
					SELECT
						p.party_Name,
						ROUND((SUM(v.votes) / total_votes) * 100, 2) AS percentage_of_votes,
						ROUND(((SUM(v.votes) / total_votes) * 100 / 100) * total_seats) AS seats_won,
						ROUND((ROUND(((SUM(v.votes) / total_votes) * 100 / 100) * total_seats) / total_seats) * 100, 2) AS percentage_of_seats,
						ROUND((SUM(v.votes) / total_votes) * 100, 2) AS percentage_of_popular_votes,
						ROUND((ROUND(((SUM(v.votes) / total_votes) * 100 / 100) * total_seats) / total_seats) * 100, 2) - ROUND((SUM(v.votes) / total_votes) * 100, 2) AS difference_between,
						(
							SELECT party_Name
							FROM (
								SELECT p.party_Name, ROUND(((SUM(v.votes) / total_votes) * 100 / 100) * total_seats, 2) AS seats_won
								FROM votes v
								JOIN parties p ON v.party_ID = p.party_ID
								CROSS JOIN (
									SELECT
										SUM(votes) AS total_votes,
										(SELECT COUNT(*) FROM constituencies) AS total_seats
									FROM votes
								) totals
								GROUP BY p.party_Name
								HAVING percentage_of_votes >= 5
								ORDER BY seats_won DESC
								LIMIT 1
							) AS subquery
						) AS party_with_most_seats,
						CASE WHEN (
							SELECT party_Name
							FROM (
								SELECT p.party_Name, ROUND(((SUM(v.votes) / total_votes) * 100 / 100) * total_seats, 2) AS seats_won
								FROM votes v
								JOIN parties p ON v.party_ID = p.party_ID
								CROSS JOIN (
									SELECT
										SUM(votes) AS total_votes,
										(SELECT COUNT(*) FROM constituencies) AS total_seats
									FROM votes
								) totals
								GROUP BY p.party_Name
								HAVING percentage_of_votes >= 5
								ORDER BY seats_won DESC
								LIMIT 1
							) AS subquery
						) = 'Conservative' THEN 'no' ELSE 'yes' END AS difference_from_real_winner
					FROM
						votes v
					JOIN
						parties p ON v.party_ID = p.party_ID
					CROSS JOIN (
						SELECT
							SUM(votes) AS total_votes,
							(SELECT COUNT(*) FROM constituencies) AS total_seats
						FROM votes
					) totals
					GROUP BY p.party_Name
					HAVING percentage_of_votes >= 5
					ORDER BY seats_won DESC;

					";

					$result = $conn->query($statement);

					if ($result) {
						// Fetch only the first row and store it in the array
						$row = $result->fetch_assoc();
						if ($row) {
							$resultsTableData[] = array(
								"year" => 2019,
								"system_Name" => "Simple Proportional Representation 5%", 
								"party" => $row["party_Name"],
								"number_of_seats" => $row["seats_won"],
								"percent_of_seats" => $row["percentage_of_seats"],
								"percent_of_pop_votes" => $row["percentage_of_popular_votes"],
								"diff_between" => $row["difference_between"],
								"party_with_most" => $row["party_with_most_seats"],
								"diff_from_winner" => $row["difference_from_real_winner"]
							);

							// Output the first row
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["seats_won"] . '</td>';
							echo '    <td>' . $row["percentage_of_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_popular_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '    <td>' . $row["party_with_most_seats"] . '</td>';
							echo '    <td>' . $row["difference_from_real_winner"] . '</td>';
							echo '  </tr>';
						}

						// Now, loop through the remaining rows to echo the rest of the table
						while ($row = $result->fetch_assoc()) {
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["seats_won"] . '</td>';
							echo '    <td>' . $row["percentage_of_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_popular_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '    <td>' . $row["party_with_most_seats"] . '</td>';
							echo '    <td>' . $row["difference_from_real_winner"] . '</td>';
							echo '  </tr>';
						}

						$result->close();
					} else {
						echo "Error executing query: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane" id="tabpanel-4" role="tabpanel" aria-labelledby="tab-4">
				<h2>Simple Proportional Representation (By County)</h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
					</tr>
					</thead>
					<tbody>
					<?php
					$statement =" 
					SELECT
						party_Name,
						total_seats,
						ROUND((total_seats / total_all_seats) * 100, 2) AS percentage_of_total_seats,
						ROUND((party_votes_total / total_votes) * 100, 2) AS percentage_of_total_votes,
						ROUND(((party_votes_total / total_votes) * 100) - ((total_seats / total_all_seats) * 100), 2) AS difference_between
					FROM (
						SELECT
							party_Name,
							SUM(seats) AS total_seats,
							SUM(party_votes) AS party_votes_total
						FROM (
							SELECT
								county,
								party_Name,
								ROUND(percentage * total_constituencies) AS seats,
								SUM(votes) AS party_votes
							FROM (
								SELECT
									c.county,
									p.party_Name,
									COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
									(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
									COUNT(v.votes) AS votes
								FROM
									votes v
								JOIN
									constituencies c ON v.constituency_ID = c.constituency_ID
								JOIN
									parties p ON v.party_ID = p.party_ID
								GROUP BY
									c.county, p.party_Name
							) AS subquery
							GROUP BY
								county, party_Name
						) AS subquery_total
						GROUP BY
							party_Name
					) AS subquery_percentage
					CROSS JOIN (
						SELECT
							SUM(ROUND(percentage * total_constituencies)) AS total_all_seats,
							SUM(votes) AS total_votes
						FROM (
							SELECT
								COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
								(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
								COUNT(v.votes) AS votes
							FROM
								votes v
						) AS subquery_total_all
					) AS subquery_all
					ORDER BY total_seats DESC;
				

					";

					$result = $conn->query($statement);

					if ($result) {
						// Fetch only the first row and store it in the array
						$row = $result->fetch_assoc();
						if ($row) {
							$resultsTableData[] = array(
								"year" => 2019,
								"system_Name" => "Simple Proportional Representation (By County)", 
								"party" => $row["party_Name"],
								"number_of_seats" => $row["total_seats"],
								"percent_of_seats" => $row["percentage_of_total_seats"],
								"percent_of_pop_votes" => $row["percentage_of_total_votes"],
								"diff_between" => $row["difference_between"],
								"party_with_most" => "",
								"diff_from_winner" => ""
							);

							// Output the first row
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						// Now, loop through the remaining rows to echo the rest of the table
						while ($row = $result->fetch_assoc()) {
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						$result->close();
					} else {
						echo "Error executing query: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane" id="tabpanel-5" role="tabpanel" aria-labelledby="tab-5">
				<h2>Simple Proportional Representation (By Region)</h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
					</tr>
					</thead>
					<tbody>
					<?php
					$statement =" 
					SELECT
						party_Name,
						total_seats,
						ROUND((total_seats / total_all_seats) * 100, 2) AS percentage_of_total_seats,
						ROUND((party_votes_total / total_votes) * 100, 2) AS percentage_of_total_votes,
						ROUND(((party_votes_total / total_votes) * 100) - ((total_seats / total_all_seats) * 100), 2) AS difference_between
					FROM (
						SELECT
							party_Name,
							SUM(seats) AS total_seats,
							SUM(party_votes) AS party_votes_total
						FROM (
							SELECT
								region,
								party_Name,
								ROUND(percentage * total_constituencies) AS seats,
								SUM(votes) AS party_votes
							FROM (
								SELECT
									c.region,
									p.party_Name,
									COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
									(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
									COUNT(v.votes) AS votes
								FROM
									votes v
								JOIN
									constituencies c ON v.constituency_ID = c.constituency_ID
								JOIN
									parties p ON v.party_ID = p.party_ID
								GROUP BY
									c.region, p.party_Name
							) AS subquery
							GROUP BY
								region, party_Name
						) AS subquery_total
						GROUP BY
							party_Name
					) AS subquery_percentage
					CROSS JOIN (
						SELECT
							SUM(ROUND(percentage * total_constituencies)) AS total_all_seats,
							SUM(votes) AS total_votes
						FROM (
							SELECT
								COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
								(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
								COUNT(v.votes) AS votes
							FROM
								votes v
						) AS subquery_total_all
					) AS subquery_all
					ORDER BY total_seats DESC;
					";

					$result = $conn->query($statement);

					if ($result) {
						// Fetch only the first row and store it in the array
						$row = $result->fetch_assoc();
						if ($row) {
							$resultsTableData[] = array(
								"year" => 2019,
								"system_Name" => "Simple Proportional Representation (By Region)", 
								"party" => $row["party_Name"],
								"number_of_seats" => $row["total_seats"],
								"percent_of_seats" => $row["percentage_of_total_seats"],
								"percent_of_pop_votes" => $row["percentage_of_total_votes"],
								"diff_between" => $row["difference_between"],
								"party_with_most" => "",
								"diff_from_winner" => ""
							);

							// Output the first row
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						// Now, loop through the remaining rows to echo the rest of the table
						while ($row = $result->fetch_assoc()) {
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						$result->close();
					} else {
						echo "Error executing query: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane" id="tabpanel-6" role="tabpanel" aria-labelledby="tab-6">
				<h2>Simple Proportional Representation (By Country)</h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
					</tr>
					</thead>
					<tbody>
					<?php
					$statement =" 
					SELECT
						party_Name,
						total_seats,
						ROUND((total_seats / total_all_seats) * 100, 2) AS percentage_of_total_seats,
						ROUND((party_votes_total / total_votes) * 100, 2) AS percentage_of_total_votes,
						ROUND(((party_votes_total / total_votes) * 100) - ((total_seats / total_all_seats) * 100), 2) AS difference_between
					FROM (
						SELECT
							party_Name,
							SUM(seats) AS total_seats,
							SUM(party_votes) AS party_votes_total
						FROM (
							SELECT
								country,
								party_Name,
								ROUND(percentage * total_constituencies) AS seats,
								SUM(votes) AS party_votes
							FROM (
								SELECT
									c.country,
									p.party_Name,
									COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
									(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
									COUNT(v.votes) AS votes
								FROM
									votes v
								JOIN
									constituencies c ON v.constituency_ID = c.constituency_ID
								JOIN
									parties p ON v.party_ID = p.party_ID
								GROUP BY
									c.country, p.party_Name
							) AS subquery
							GROUP BY
								country, party_Name
						) AS subquery_total
						GROUP BY
							party_Name
					) AS subquery_percentage
					CROSS JOIN (
						SELECT
							SUM(ROUND(percentage * total_constituencies)) AS total_all_seats,
							SUM(votes) AS total_votes
						FROM (
							SELECT
								COUNT(v.votes) / (SELECT COUNT(*) FROM votes) AS percentage,
								(SELECT COUNT(DISTINCT constituency_ID) FROM constituencies) AS total_constituencies,
								COUNT(v.votes) AS votes
							FROM
								votes v
						) AS subquery_total_all
					) AS subquery_all
					ORDER BY total_seats DESC;
					";

					$result = $conn->query($statement);

					if ($result) {
						// Fetch only the first row and store it in the array
						$row = $result->fetch_assoc();
						if ($row) {
							$resultsTableData[] = array(
								"year" => 2019,
								"system_Name" => "Simple Proportional Representation (By Country)", 
								"party" => $row["party_Name"],
								"number_of_seats" => $row["total_seats"],
								"percent_of_seats" => $row["percentage_of_total_seats"],
								"percent_of_pop_votes" => $row["percentage_of_total_votes"],
								"diff_between" => $row["difference_between"],
								"party_with_most" => "",
								"diff_from_winner" => ""
							);

							// Output the first row
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						// Now, loop through the remaining rows to echo the rest of the table
						while ($row = $result->fetch_assoc()) {
							echo '  <tr>';
							echo '    <td>' . $row["party_Name"] . '</td>';
							echo '    <td>' . $row["total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_seats"] . '</td>';
							echo '    <td>' . $row["percentage_of_total_votes"] . '</td>';
							echo '    <td>' . $row["difference_between"] . '</td>';
							echo '  </tr>';
						}

						$result->close();
					} else {
						echo "Error executing query: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
			<div class="tab-pane active" id="tabpanel-0" role="tabpanel" aria-labelledby="tab-0">
				<h2>Data Overview</h2>
				<table class="table">
					<thead>
					<tr>
						<th scope="col">Year</th>
						<th scope="col">System</th>
						<th scope="col">Party</th>
						<th scope="col">No Of Seats</th>
						<th scope="col">% Of Seats</th>			      
						<th scope="col">% Of Popular Votes</th>
						<th scope="col">Difference between % Of PopSeats & PopVotes</th>			      
						<th scope="col">Party with most seats.</th>
						<th scope="col">Different from actual winner.</th>
					</tr>
					</thead>
					<tbody>
					<?php
					foreach ($resultsTableData as $data) {
						echo '  <tr>';
						echo '    <td>' . $data["year"] . '</td>';
						echo '    <td>' . $data["system_Name"] . '</td>';
						echo '    <td>' . $data["party"] . '</td>';
						echo '    <td>' . $data["number_of_seats"] . '</td>';
						echo '    <td>' . $data["percent_of_seats"] . '</td>';
						echo '    <td>' . $data["percent_of_pop_votes"] . '</td>';
						echo '    <td>' . $data["diff_between"] . '</td>';
						echo '    <td>' . $data["party_with_most"] . '</td>';
						echo '    <td>' . $data["diff_from_winner"] . '</td>';
						echo '  </tr>';
					}


					// Empty the Results table if it exists
					$sqlEmptyTable = "DROP TABLE IF EXISTS Results";
					if ($conn->query($sqlEmptyTable) === TRUE) {

						// Create Results table
						$sqlCreateTable = "CREATE TABLE IF NOT EXISTS Results (
							id INT(11) AUTO_INCREMENT PRIMARY KEY,
							year INT(4),
							system_Name VARCHAR(255),
							party VARCHAR(255),
							number_of_seats INT(11),
							percent_of_seats FLOAT,
							percent_of_pop_votes FLOAT,
							diff_between INT(11),
							party_with_most VARCHAR(255),
							diff_from_winner INT(11)
						)";

						if ($conn->query($sqlCreateTable) === TRUE) {
							foreach ($resultsTableData as $data) {
								// Insert data into Results table
								$sqlInsertData = "INSERT INTO Results 
									(year, system_Name, party, number_of_seats, percent_of_seats, percent_of_pop_votes, diff_between, party_with_most, diff_from_winner) 
									VALUES 
									('{$data["year"]}', '{$data["system_Name"]}', '{$data["party"]}', '{$data["number_of_seats"]}', '{$data["percent_of_seats"]}', '{$data["percent_of_pop_votes"]}', '{$data["diff_between"]}', '{$data["party_with_most"]}', '{$data["diff_from_winner"]}')";
						
								if ($conn->query($sqlInsertData) !== TRUE) {
									echo "Error inserting data: " . $conn->error;
								}
							}
						
							echo "Data inserted successfully!";
						} else {
							echo "Error creating table: " . $conn->error;
						}

					} else {
						echo "Error emptying table: " . $conn->error;
					}

					?>
					</tbody>
				</table>
			</div>
		</div>
		<?php $conn->close(); ?>
	</body>
</html>	
