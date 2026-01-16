// Polyfill for Array.indexOf, from MDN.  Covers a lot of cases we don't care about.
if (!Array.prototype.indexOf)
  Array.prototype.indexOf =
  (function(Object, max, min) {
    "use strict"
    return function indexOf(member, fromIndex) {
      if (this === null || this === undefined)
        throw TypeError("Array.prototype.indexOf called on null or undefined")

      var that = Object(this), Len = that.length >>> 0, i = min(fromIndex | 0, Len)
      if (i < 0) i = max(0, Len + i)
      else if (i >= Len) return -1

      if (member === void 0) {        // undefined
        for (; i !== Len; ++i) if (that[i] === void 0 && i in that) return i
      } else if (member !== member) { // NaN
        return -1 // Since NaN !== NaN, it will never be found. Fast-path it.
      } else                          // all else
        for (; i !== Len; ++i) if (that[i] === member) return i 

      return -1 // if the value was not found, then return -1
    }
  })(Object, Math.max, Math.min)

function set_up_ballot() {
  $("#awards .award-card").addClass("d-none");
  for (var awardid in g_ballot) {
    var award_ballot = g_ballot[awardid];
    $(".please-click").removeClass('d-none');
    var card = $("#awards .award-card[data-awardid=" + awardid + "]");
    card.removeClass("d-none");
    card.find(".please-vote-count").text(award_ballot['max_votes']);

    // Clear existing selections in the card
    var selectionsContainer = card.find(".selections-container .d-flex");
    selectionsContainer.empty();

    var award_ballot_votes = award_ballot['votes'];
    for (var i = 0; i < award_ballot_votes.length; ++i) {
      var racerid = award_ballot_votes[i];
      var thumbnail = $("<img>")
          .addClass("selection-thumbnail rounded border")
          .attr('src', thumbnail_url_for_racerid(racerid))
          .attr('alt', 'Car #' + car_number_for_racerid(racerid));
      
      var selectionBadge = $("<div class='position-relative d-inline-block'></div>")
        .append(thumbnail)
        .append("<span class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success'>" + 
                car_number_for_racerid(racerid) + "</span>");
      
      selectionsContainer.append(selectionBadge);
    }
  }
  $("#no-awards").toggleClass('d-none', $(".award-card").not('.d-none').length != 0);
}

// From the main screen, clicking on an award opens the "racers" modal
function click_one_award(card) {
  g_awardid = card.attr('data-awardid');
  write_racers_headline();
  set_full_ballot_message();

  var award_name = card.find(".card-title").text();
  $("#racer_view_award_name").text(award_name);

  // Show all racers initially
  $("#racers .racer-card").removeClass('d-none');

  // Filter eligible racers
  var classids = card.attr('data-eligible-classids').split(',');
  var rankids = card.attr('data-eligible-rankids').split(',');
  
  $("#racers .racer-card").each(function() {
    var racerCard = $(this);
    var isEligible = classids.indexOf(racerCard.attr('data-classid')) >= 0 &&
                    rankids.indexOf(racerCard.attr('data-rankid')) >= 0;
    racerCard.closest('.col-6, .col-sm-4, .col-md-3, .col-lg-2').toggleClass('d-none', !isEligible);
  });
  
  // Show the modal using Bootstrap 5
  var modal = new bootstrap.Modal(document.getElementById('racers_modal'));
  modal.show();
}

function write_racers_headline() {
  var selectedContainer = $("#selected-list");
  selectedContainer.empty();
  
  var selectionsDisplay = $("#selections-display");

  var award_ballot_votes = g_ballot[g_awardid]['votes'];
  
  if (award_ballot_votes.length > 0) {
    selectionsDisplay.removeClass('d-none');
    
    for (var i = 0; i < award_ballot_votes.length; ++i) {
      var racerid = award_ballot_votes[i];
      var thumbnail = $("<img>")
          .addClass("selection-thumbnail rounded border me-1")
          .attr('src', thumbnail_url_for_racerid(racerid))
          .attr('alt', 'Car #' + car_number_for_racerid(racerid));
      
      var selectionItem = $("<div class='d-inline-block position-relative me-2'></div>")
        .append(thumbnail)
        .append("<span class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary'>" + 
                car_number_for_racerid(racerid) + "</span>")
        .attr('data-racerid', racerid)
        .css('cursor', 'pointer')
        .on('click', function() {
          show_racer_view_modal($(this));
        });
      
      selectedContainer.append(selectionItem);
    }
  } else {
    selectionsDisplay.addClass('d-none');
  }
}

// Close-up view of a single racer, along with the checkbox to vote this racer in or out
function show_racer_view_modal(element) {
  g_racerid = parseInt(element.attr('data-racerid'));
  set_full_ballot_message();
  
  var isVoted = g_ballot[g_awardid]['votes'].includes(g_racerid);
  set_checkbox(isVoted);
  
  $("#racer_view_carnumber").text("#" + car_number_for_racerid(g_racerid));
  $("#racer_view_photo").attr('src', photo_url_for_racerid(g_racerid));
  
  // Show the modal using Bootstrap 5
  var modal = new bootstrap.Modal(document.getElementById('racer_view_modal'));
  modal.show();
}

function toggle_vote(element) {
  var award_ballot = g_ballot[g_awardid];
  var isCurrentlyVoted = award_ballot['votes'].includes(g_racerid);
  
  if (isCurrentlyVoted) {
    // Remove vote
    award_ballot['votes'] = award_ballot['votes'].filter(function(v) { return v != g_racerid; });
    set_checkbox(false);
  } else if (award_ballot['votes'].length >= award_ballot['max_votes']) {
    // Show warning - ballot is full
    console.log("Full ballot!");
    return;
  } else {
    // Add vote
    award_ballot['votes'].push(g_racerid);
    set_checkbox(true);
  }

  // Send vote to server
  $.ajax('action.php', {
    type: 'POST',
    data: {
      action: 'vote.cast',
      awardid: g_awardid,
      'votes': JSON.stringify(award_ballot['votes'])
    }
  });

  write_racers_headline();
  set_up_ballot();
}
    
function set_checkbox(checked) {
  var checkbox = $("#racer_view_check");
  checkbox.prop('checked', checked);
}

function set_full_ballot_message() {
  var award_ballot = g_ballot[g_awardid];
  var max_votes = award_ballot['max_votes'];

  $("#racer_view_max_votes").text(max_votes);
  $("#full-ballot-max").text(max_votes);

  var isRacerVoted = award_ballot['votes'].includes(g_racerid);
  var isBallotFull = award_ballot['votes'].length >= max_votes;
  
  $("#full-ballot").toggleClass('d-none', isRacerVoted || !isBallotFull);
}

function thumbnail_url_for_racerid(racerid) {
  var racerCard = $(".racer-card[data-racerid=" + racerid + "]");
  var img = racerCard.find('img.racer-photo');
  return img.length > 0 ? img.attr('src') : 'img/placeholder-car.png';
}

function photo_url_for_racerid(racerid) {
  return $(".racer-card[data-racerid=" + racerid + "]").attr('data-img');
}

function car_number_for_racerid(racerid) {
  return $(".racer-card[data-racerid=" + racerid + "] .car-number-badge").text().replace('#', '');
}

// Bootstrap 5 compatible modal functions
function close_modal(selector) {
  var modalId = selector.replace('#', '');
  var modalElement = document.getElementById(modalId);
  if (modalElement) {
    var modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) modal.hide();
  }
}

function close_secondary_modal(selector) {
  close_modal(selector);
}

function show_modal(selector, callback) {
  var modalId = selector.replace('#', '');
  var modalElement = document.getElementById(modalId);
  if (modalElement) {
    var modal = new bootstrap.Modal(modalElement);
    modal.show();
    if (callback) callback();
  }
}

// Password handling function
function get_ballot() {
  $.ajax('action.php', {
    type: 'GET',
    data: {
      query: 'ballot.get',
      password: $("#password_input").val()
    },
    success: function(data) {
      var passwordModal = bootstrap.Modal.getInstance(document.getElementById('password_modal'));
      var isPasswordModalShowing = passwordModal && document.getElementById('password_modal').classList.contains('show');
      
      if (data.hasOwnProperty('outcome') && data.outcome.summary == 'failure') {
        $("#awards .award-card").addClass('d-none');
        if (data.outcome.code == 'password') {
          $("#wrong-password").toggleClass('d-none', !isPasswordModalShowing);
          if (!isPasswordModalShowing) {
            var modal = new bootstrap.Modal(document.getElementById('password_modal'));
            modal.show();
          }
        }
      } else {
        g_ballot = data.ballot;
        if (passwordModal) passwordModal.hide();
        set_up_ballot();
      }
    }
  });
}

// Polling for ballot status changes
$(function() {
  var balloting_open_or_closed = '';
  
  // Handle password form submission
  $('#password-form').on('submit', function(e) {
    e.preventDefault();
    get_ballot();
  });
  
  // Poll for balloting status changes
  setInterval(function() {
    $.ajax('action.php', {
      type: 'GET',
      data: {
        query: 'settings.list',
        key: 'balloting'
      },
      success: function(data) {
        var v = (data.hasOwnProperty('settings') ? data.settings.balloting : false) || 'closed'
        if (balloting_open_or_closed == '') {
          balloting_open_or_closed = v;
        } else if (balloting_open_or_closed != v) {
          location.reload(true);
        }
      }
    });
  }, 5000);
});