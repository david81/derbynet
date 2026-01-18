<?php @session_start();

require_once('inc/data.inc');
require_once('inc/authorize.inc');
session_write_close();
require_once('inc/banner.inc');
require_once('inc/photo-config.inc');
require_once('inc/awards.inc');
require_once('inc/voterid.inc');
require_once('inc/standings.inc');
require_once('inc/schema_version.inc');

$is_open = read_raceinfo('balloting', 'closed') == 'open';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Ballot</title>
    <!-- Bootstrap 5 CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        .award-card {
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 120px;
        }

        .award-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .racer-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .racer-card:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .racer-photo {
            height: 120px;
            object-fit: cover;
        }

        .car-number-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
        }

        .selection-thumbnail {
            /* width: 60px; */
            height: 60px;
            object-fit: cover;
        }

        .voted-for {
            border: 3px solid var(--bs-success);
            box-shadow: 0 0 10px var(--bs-success);


        }

        /* .voting-section {
  max-width: 800px;
} */
        @media (max-width: 768px) {
            .racer-photo {
                height: 100px;
            }

            .selection-thumbnail {
                /* width: 50px; */
                height: 50px;
            }
        }
    </style>
    <script type="text/javascript" src="js/ajax-setup.js"></script>
    <script type="text/javascript" src="js/vote.js"></script>
    <script type="text/javascript">
        let g_ballot;
        let g_awardid;
        let g_racerid;
        <?php if ($is_open) { ?>
            document.addEventListener('DOMContentLoaded', function () {
                get_ballot();
            });
        <?php } ?>
    </script>
    <?php require('inc/stylesheet.inc'); ?>
</head>

<body class="bg-light">
    <?php
    make_banner('Ballot');

    $order = '';
    if (isset($_GET['order']))
        $order = $_GET['order'];  // Values are: name, class, car
    if (!$order)
        $order = 'car';

    function link_for_ordering($key, $text)
    {
        global $order;
        echo "<a ";
        if ($order == $key) {
            echo 'class="current_sort"';
        }
        echo " href='vote.php?order=" . $key . "'>";
        echo $text;
        echo "</a>";
    }

    $racers = array();

    $sql = 'SELECT racerid, carnumber, lastname, firstname,'
        . ' RegistrationInfo.classid, class, RegistrationInfo.rankid, rank, imagefile,'
        . ' ' . (schema_version() < 2 ? "class" : "Classes.sortorder") . ' AS class_sort, '
        . (schema_version() < 2 ? '\'\' as ' : '') . ' carphoto'
        . ' FROM ' . inner_join(
        'RegistrationInfo',
        'Classes',
        'Classes.classid = RegistrationInfo.classid',
        'Ranks',
        'Ranks.rankid = RegistrationInfo.rankid'
    )
        . ' WHERE passedinspection = 1 AND exclude = 0'
        . ' ORDER BY '
        . ($order == 'car' ? 'carnumber, lastname, firstname' :
            ($order == 'class' ? 'class_sort, lastname, firstname' :
                'lastname, firstname'));

    foreach ($db->query($sql) as $rs) {
        $racerid = $rs['racerid'];
        $racers[$racerid] = array(
            'racerid' => $racerid,
            'carnumber' => $rs['carnumber'],
            'lastname' => $rs['lastname'],
            'firstname' => $rs['firstname'],
            'classid' => $rs['classid'],
            'class' => $rs['class'],
            'rankid' => $rs['rankid'],
            'rank' => $rs['rank'],
            'imagefile' => $rs['imagefile'],
            'carphoto' => $rs['carphoto']
        );
    }

    ?>

    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">

                <!-- Balloting Status -->
                <div id="balloting-closed" <?php if ($is_open)
                    echo 'class="d-none"'; ?>>
                    <div class="alert alert-warning text-center" role="alert">
                        <h2 class="alert-heading mb-0">Balloting is currently closed</h4>
                    </div>
                </div>

                <!-- No Awards Message -->
                <div id='no-awards' class='d-none'>
                    <div class="alert alert-info text-center" role="alert">
                        <h2 class="alert-heading mb-0">There are no awards available for voting</h2>
                    </div>
                </div>

                <!-- Awards Section -->
                <div id="awards" class="voting-section mx-auto">
                    <div id='please-click' class='d-none mb-4'>
                        <h2 class="alert alert-primary text-center" role="alert">
                            Please click on an award to vote
                        </h2>
                    </div>

                    <div class="row g-3">
                        <?php
                        // This enumerates all awards, not just those that are ballotable.
                        $awards = all_awards(false);
                        mark_award_eligibility($awards);
                        foreach ($awards as $award) {
                            echo "<div class='col-12 col-md-3'>";
                            echo "<div class='card award-card d-none h-100' data-awardname='" . htmlspecialchars($award['awardname'], ENT_QUOTES, 'UTF-8') . "' data-awardid='$award[awardid]' data-bs-toggle='modal' data-bs-target='#racers_modal'";
                            if ($award['classid'] != 0) {
                                echo " data-classid='$award[classid]'";
                            }
                            if ($award['rankid'] != 0) {
                                echo " data-rankid='$award[rankid]'";
                            }
                            echo " data-eligible-classids='" . implode(',', $award['eligible-classids']) . "'";
                            echo " data-eligible-rankids='" . implode(',', $award['eligible-rankids']) . "'";
                            echo " >";

                            echo "<div class='card-body text-center d-flex flex-column'>";
                            echo "<h3 class='card-title text-primary mb-3'>" . htmlspecialchars($award['awardname'], ENT_QUOTES, 'UTF-8') . "</h5>";
                            // echo "<p class='card-text text-muted mb-3'>Please vote for no more than <span class='badge bg-primary please-vote-count'>UNSET</span>.</p>";
                        
                            echo "<div class='selections-container m-auto'>";
                            echo "<div class='d-flex flex-wrap justify-content-center gap-4' id='selections-$award[awardid]'>";
                            echo "<!-- Selections will be added here dynamically -->";
                            echo "</div>";
                            echo "</div>";

                            echo "</div>"; // card-body
                            echo "</div>"; // card
                            echo "</div>"; // col
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Racers Selection Modal -->
    <div class="modal fade" id="racers_modal" tabindex="-1" aria-labelledby="racersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white px-4">
                    <div class="modal-title w-100 text-center lh-1" id="racersModalLabel">
                        <h3 id="all_racer_view_award_name">Award Name</h3>

                        <p class="m-0"> You have <span id="racer_view_votes_available">0</span> of <span
                                id="racer_view_max_votes">0</span> votes available</p>
                    </div>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">


                    <div id="racers" class="row g-3">
                        <?php
                        $use_subgroups = read_raceinfo_boolean('use-subgroups');

                        foreach ($racers as $racer) {
                            echo "<div class='col-6 col-sm-4 col-md-3 col-lg-2'>";
                            echo "<div class='card racer-card h-100 position-relative'"
                                . " data-bs-toggle='modal' data-bs-target='#racer_view_modal'"
                                . " data-racerid='$racer[racerid]'"
                                . " data-classid='$racer[classid]'"
                                . " data-rankid='$racer[rankid]'"
                                . " data-carnumber='$racer[carnumber]'"
                                . " data-img='" . car_photo_repository()->url_for_racer($racer, RENDER_WORKING) . "'"
                                . " >";

                            if ($racer['carphoto']) {
                                echo "<img src='" . car_photo_repository()->lookup(RENDER_JUDGING)->render_url($racer['carphoto']) . "' class='card-img-top racer-photo' alt='Car Photo'/>";
                            } else {
                                echo "<div class='card-img-top racer-photo bg-light d-flex align-items-center justify-content-center'>";
                                echo "<i class='bi bi-camera text-muted' style='font-size: 2rem;'></i>";
                                echo "</div>";
                            }

                            echo "<div class='badge bg-primary position-absolute top-0 end-0'><h6 class='m-0'>#$racer[carnumber]</h6></div>";

                            echo "<div class='card-body p-2 text-center'>";
                            echo "<h6 class='card-title mb-1 text-truncate' style='font-size: 0.8rem;'>"
                                . htmlspecialchars($racer['class'], ENT_QUOTES, 'UTF-8') . "</h6>";

                            if ($use_subgroups) {
                                echo "<small class='text-muted d-block text-truncate'>"
                                    . htmlspecialchars($racer['rank'], ENT_QUOTES, 'UTF-8') . "</small>";
                            }
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Individual Racer View Modal -->
    <div class="modal fade" id="racer_view_modal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="racerViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2 bg-primary text-white">

                    <h3 class="modal-title w-100 text-center" id="racerViewModalLabel">

                        <span id="racer_view_award_name">Award Name</span>
                    </h3>
                    <button type="button" class="btn btn-close btn-close-white" data-bs-toggle='modal'
                        data-bs-target="#racers_modal">
                    </button>
                </div>
                <div class="modal-body text-center">


                    <div class="card">
                        <div class="card-header">
                            <h3 id="racer_view_carnumber" class="card-title mb-0">#UNSET</h3>
                        </div>
                        <div class="card-body">
                            <img id="racer_view_photo" class="img-fluid rounded shadow" style="max-height: 400px;"
                                alt="Car Photo" />
                        </div>

                        <div class="card-footer">
                            <button id="racer-vote-btn" class="btn btn-lg btn-success" onclick="toggle_vote(this);">
                                Vote for this racer?
                            </button>
                            <button id="racer-unvote-btn" class="btn btn-lg btn-danger" onclick="toggle_vote(this);">
                                Remove vote for this racer?
                            </button>
                            <div class="alert alert-warning mt-3 d-none" id="full-ballot">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                You already have <span id="full-ballot-max" class="fw-bold">UNSET</span> racer(s)
                                chosen.
                            </div>
                        </div>

                    </div>


                </div>

            </div>
        </div>
    </div>

    <!-- Password Modal -->
    <div class="modal fade" id="password_modal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel">
                        <i class="bi bi-shield-lock me-2"></i>Ballot Access
                    </h5>
                </div>
                <form id="password-form">
                    <div class="modal-body">
                        <h6 class="mb-3">Please enter the ballot password</h6>
                        <div class="mb-3">
                            <input id="password_input" type="password" class="form-control form-control-lg"
                                placeholder="Enter password" autocomplete="current-password" />
                        </div>
                        <div class="alert alert-danger d-none" id="wrong-password">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            The password you entered is incorrect. Please try again.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key me-1"></i>Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="js/bootstrap.bundle.min.js"></script>

    <script>
        // Event listeners for modals
        const allRacersModal = document.getElementById('racers_modal');
        if (allRacersModal) {
            allRacersModal.addEventListener('show.bs.modal', event => {
                // card that triggered the modal
                const card = event.relatedTarget
                //check if the card has the award-card class
                let isAwardCard = card.classList.contains('award-card');
                //if the event target was an award card, get the awardid from the card and rebuild the racer list
                if (isAwardCard) {
                    g_awardid = card.getAttribute('data-awardid');
                    let currentVotes = g_ballot[g_awardid]['votes'];

                    var award_name = card.getAttribute('data-awardname');
                    document.getElementById('all_racer_view_award_name').textContent = award_name;

                    // Show all racers initially
                    document.querySelectorAll("#racers .racer-card").forEach(el => el.classList.remove('d-none'));

                    // Filter eligible racers
                    var classids = card.getAttribute('data-eligible-classids').split(',');
                    var rankids = card.getAttribute('data-eligible-rankids').split(',');

                    document.querySelectorAll("#racers .racer-card").forEach(function (racerCard) {
                        let racerId = racerCard.getAttribute('data-racerid');
                        //if the racer is already voted for, show a checkmark or highlight them
                        if (currentVotes.includes(parseInt(racerId))) {
                            racerCard.classList.add('voted-for');
                        } else {
                            racerCard.classList.remove('voted-for');
                        }
                        let isEligible = classids.indexOf(racerCard.getAttribute('data-classid')) >= 0 &&
                            rankids.indexOf(racerCard.getAttribute('data-rankid')) >= 0;
                        let colWrapper = racerCard.closest('.col-6, .col-sm-4, .col-md-3, .col-lg-2');
                        if (colWrapper) {
                            if (isEligible) {
                                colWrapper.classList.remove('d-none');
                            } else {
                                colWrapper.classList.add('d-none');
                            }
                        }
                    });

                    set_ballot_status_info();
                }
            })
        }

        const racerViewModal = document.getElementById('racer_view_modal');
        if (racerViewModal) {
            racerViewModal.addEventListener('show.bs.modal', event => {
                // card that triggered the modal
                const card = event.relatedTarget

                g_racerid = parseInt(card.getAttribute('data-racerid'));
                let award_name = document.querySelector(
                    `.award-card[data-awardid='${g_awardid}']`).getAttribute('data-awardname');

                document.getElementById('racer_view_award_name').textContent = award_name;

                document.getElementById("racer_view_carnumber").textContent = "#" + car_number_for_racerid(
                    g_racerid);
                document.getElementById("racer_view_photo").setAttribute('src', photo_url_for_racerid(g_racerid));

                set_ballot_status_info();

            })
        }
    </script>

</body>

</html>