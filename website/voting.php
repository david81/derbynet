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

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Award Ballot</title>
<!-- Bootstrap 5 CSS -->
<link href="css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="css/bootstrap-icons.css" rel="stylesheet">
<!-- Custom CSS -->
<style>
.award-card {
  transition: all 0.3s ease;
  cursor: pointer;
  min-height: 120px;
}
.award-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.racer-card {
  transition: all 0.2s ease;
  cursor: pointer;
}
.racer-card:hover {
  transform: scale(1.02);
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
  width: 60px;
  height: 60px;
  object-fit: cover;
}
.voting-section {
  max-width: 800px;
}
@media (max-width: 768px) {
  .racer-photo { height: 100px; }
  .selection-thumbnail { width: 50px; height: 50px; }
}
</style>
<script type="text/javascript" src="js/jquery.js"></script>
<script type="text/javascript" src="js/ajax-setup.js"></script>
<script type="text/javascript" src="js/vote-bootstrap.js"></script>
<script type="text/javascript">
var g_ballot;
var g_awardid;
var g_racerid;
<?php if ($is_open) { ?>
  $(function() { get_ballot(); });
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

function link_for_ordering($key, $text) {
  global $order;
  echo "<a ";
  if ($order == $key) {
    echo 'class="current_sort"';
  }
  echo " href='voting.php?order=".$key."'>";
  echo $text;
  echo "</a>";
}

$racers = array();

$sql = 'SELECT racerid, carnumber, lastname, firstname,'
      .' RegistrationInfo.classid, class, RegistrationInfo.rankid, rank, imagefile,'
      .' '.(schema_version() < 2 ? "class" : "Classes.sortorder").' AS class_sort, '
      .(schema_version() < 2 ? '\'\' as ' : '').' carphoto'
      .' FROM '.inner_join('RegistrationInfo',
                           'Classes', 'Classes.classid = RegistrationInfo.classid',
                           'Ranks', 'Ranks.rankid = RegistrationInfo.rankid')
      .' WHERE passedinspection = 1 AND exclude = 0'
      .' ORDER BY '
      .($order == 'car' ? 'carnumber, lastname, firstname' :
        ($order == 'class'  ? 'class_sort, lastname, firstname' :
         'lastname, firstname'));

foreach ($db->query($sql) as $rs) {
  $racerid = $rs['racerid'];
  $racers[$racerid] = array('racerid' => $racerid,
							'carnumber' => $rs['carnumber'],
							'lastname' => $rs['lastname'],
							'firstname' => $rs['firstname'],
                            'classid' => $rs['classid'],
							'class' => $rs['class'],
                            'rankid' => $rs['rankid'],
                            'rank' => $rs['rank'],
                            'imagefile' => $rs['imagefile'],
                            'carphoto' => $rs['carphoto']);
}

?>

<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      
      <!-- Balloting Status -->
      <div id="balloting-closed" <?php if ($is_open) echo 'class="d-none"'; ?>>
        <div class="alert alert-warning text-center" role="alert">
          <i class="bi bi-lock-fill me-2"></i>
          <h4 class="alert-heading mb-0">Balloting is currently closed</h4>
        </div>
      </div>
      
      <!-- No Awards Message -->
      <div id='no-awards' class='d-none'>
        <div class="alert alert-info text-center" role="alert">
          <i class="bi bi-info-circle-fill me-2"></i>
          <h4 class="alert-heading mb-0">There are no awards available for voting</h4>
        </div>
      </div>
      
      <!-- Awards Section -->
      <div id="awards" class="voting-section mx-auto">
        <div class='please-click d-none mb-4'>
          <div class="alert alert-primary text-center" role="alert">
            <i class="bi bi-hand-index-thumb me-2"></i>
            Please click on an award to vote
          </div>
        </div>
        
        <div class="row g-3">
          <?php
          // This enumerates all awards, not just those that are ballotable.
          $awards = all_awards(false);
          mark_award_eligibility($awards);
          foreach ($awards as $award) {
            echo "<div class='col-12 col-md-6'>";
            echo "<div class='card award-card d-none h-100' data-awardid='$award[awardid]'";
            if ($award['classid'] != 0) {
              echo " data-classid='$award[classid]'";
            }
            if ($award['rankid'] != 0) {
              echo " data-rankid='$award[rankid]'";
            }
            echo " data-eligible-classids='".implode(',', $award['eligible-classids'])."'";
            echo " data-eligible-rankids='".implode(',', $award['eligible-rankids'])."'";
            echo " onclick='click_one_award($(this));'>";
            
            echo "<div class='card-body text-center d-flex flex-column'>";
            echo "<h5 class='card-title text-primary mb-3'>".htmlspecialchars($award['awardname'], ENT_QUOTES, 'UTF-8')."</h5>";
            echo "<p class='card-text text-muted mb-3'>Please vote for no more than <span class='badge bg-primary please-vote-count'>UNSET</span>.</p>";
            
            echo "<div class='selections-container mt-auto'>";
            echo "<div class='d-flex flex-wrap justify-content-center gap-2' id='selections-$award[awardid]'>";
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
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="racersModalLabel">
          <i class="bi bi-trophy me-2"></i>
          Choose up to <span id="racer_view_max_votes" class="badge bg-light text-dark">0</span> for 
          <span id="racer_view_award_name">Award Name</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="selected_racers" class="mb-3">
          <div class="alert alert-success d-none" id="selections-display">
            <strong>Selected:</strong>
            <div id="selected-list" class="d-flex flex-wrap gap-2 mt-2"></div>
          </div>
        </div>
        
        <div id="racers" class="row g-3">
          <?php
          $use_subgroups = read_raceinfo_boolean('use-subgroups');
          
          foreach ($racers as $racer) {
            echo "<div class='col-6 col-sm-4 col-md-3 col-lg-2'>";
            echo "<div class='card racer-card h-100 position-relative'"
                ." data-racerid='$racer[racerid]'"
                ." data-classid='$racer[classid]'"
                ." data-rankid='$racer[rankid]'"
                ." data-img='".car_photo_repository()->url_for_racer($racer, RENDER_WORKING)."'"
                ." onclick='show_racer_view_modal($(this));'>";
            
            if ($racer['carphoto']) {
              echo "<img src='".car_photo_repository()->lookup(RENDER_JUDGING)->render_url($racer['carphoto'])."' class='card-img-top racer-photo' alt='Car Photo'/>";
            } else {
              echo "<div class='card-img-top racer-photo bg-light d-flex align-items-center justify-content-center'>";
              echo "<i class='bi bi-camera text-muted' style='font-size: 2rem;'></i>";
              echo "</div>";
            }
            
            echo "<span class='badge bg-primary car-number-badge'>#$racer[carnumber]</span>";
            
            echo "<div class='card-body p-2 text-center'>";
            echo "<h6 class='card-title mb-1 text-truncate' style='font-size: 0.8rem;'>"
                .htmlspecialchars($racer['class'], ENT_QUOTES, 'UTF-8')."</h6>";
            
            if ($use_subgroups) {
              echo "<small class='text-muted d-block text-truncate'>"
                  .htmlspecialchars($racer['rank'], ENT_QUOTES, 'UTF-8')."</small>";
            }
            echo "</div>";
            echo "</div>";
            echo "</div>";
          }
          ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Individual Racer View Modal -->
<div class="modal fade" id="racer_view_modal" tabindex="-1" aria-labelledby="racerViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="racerViewModalLabel">
          <span id="racer_view_carnumber" class="badge bg-primary me-2">#000</span>
          Racer Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-3">
          <div class="form-check form-switch d-inline-block">
            <input class="form-check-input" type="checkbox" id="racer_view_check" style="transform: scale(1.5);">
            <label class="form-check-label fs-5 ms-2" for="racer_view_check">
              <i class="bi bi-hand-thumbs-up me-1"></i>Vote for this racer
            </label>
          </div>
        </div>
        
        <img id="racer_view_photo" class="img-fluid rounded shadow" style="max-height: 400px;" alt="Car Photo"/>
        
        <div class="alert alert-warning mt-3 d-none" id="full-ballot">
          <i class="bi bi-exclamation-triangle me-2"></i>
          You already have <span id="full-ballot-max" class="fw-bold">UNSET</span> racer(s) chosen.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Close
        </button>
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
            <input id="password_input" type="password" class="form-control form-control-lg" placeholder="Enter password" autocomplete="current-password"/>
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
// Update jQuery modal functions to work with Bootstrap 5
function close_modal(selector) {
  const modalId = selector.replace('#', '');
  const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
  if (modal) modal.hide();
}

function close_secondary_modal(selector) {
  close_modal(selector);
}

function show_modal(selector) {
  const modalId = selector.replace('#', '');
  const modal = new bootstrap.Modal(document.getElementById(modalId));
  modal.show();
}

// Update the voting checkbox functionality
$(document).ready(function() {
  $('#racer_view_check').on('change', function() {
    toggle_vote($(this));
  });
  
  // Handle password form submission
  $('#password-form').on('submit', function(e) {
    e.preventDefault();
    // Your existing password handling logic here
  });
});
</script>

</body>
</html>