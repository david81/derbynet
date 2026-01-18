// Polyfill for Array.indexOf, from MDN.  Covers a lot of cases we don't care about.
if (!Array.prototype.indexOf)
  Array.prototype.indexOf = (function (Object, max, min) {
    "use strict";
    return function indexOf(member, fromIndex) {
      if (this === null || this === undefined)
        throw TypeError("Array.prototype.indexOf called on null or undefined");

      var that = Object(this),
        Len = that.length >>> 0,
        i = min(fromIndex | 0, Len);
      if (i < 0) i = max(0, Len + i);
      else if (i >= Len) return -1;

      if (member === void 0) {
        // undefined
        for (; i !== Len; ++i) if (that[i] === void 0 && i in that) return i;
      } else if (member !== member) {
        // NaN
        return -1; // Since NaN !== NaN, it will never be found. Fast-path it.
      } // all else
      else for (; i !== Len; ++i) if (that[i] === member) return i;

      return -1; // if the value was not found, then return -1
    };
  })(Object, Math.max, Math.min);

function set_up_ballot() {
  // Hide all award cards
  document
    .querySelectorAll("#awards .award-card")
    .forEach((card) => card.classList.add("d-none"));

  // Show "please click" message if we have awards to vote on
  document
    .getElementById("please-click")
    .classList.toggle("d-none", Object.keys(g_ballot).length === 0);

  // For each award in the ballot, show the card and populate selections
  for (var awardid in g_ballot) {
    var award_ballot = g_ballot[awardid];

    var card = document.querySelector(
      "#awards .award-card[data-awardid='" + awardid + "']",
    );
    card.classList.remove("d-none");

    // Clear existing selections in the card
    var selectionsContainer = card.querySelector(
      ".selections-container .d-flex",
    );
    selectionsContainer.innerHTML = "";

    var award_ballot_votes = award_ballot["votes"];
    for (var i = 0; i < award_ballot_votes.length; ++i) {
      var racerid = award_ballot_votes[i];
      var thumbnail = document.createElement("img");
      thumbnail.className = "selection-thumbnail rounded border";
      thumbnail.src = thumbnail_url_for_racerid(racerid);
      thumbnail.alt = "Car #" + car_number_for_racerid(racerid);

      var selectionBadge = document.createElement("div");
      selectionBadge.className = "position-relative d-inline-block";
      selectionBadge.appendChild(thumbnail);
      selectionBadge.insertAdjacentHTML(
        "beforeend",
        "<div class='position-absolute top-0 start-100 translate-middle badge bg-primary'><h6 class='m-0'>#" +
          car_number_for_racerid(racerid) +
          "</h6></div>",
      );

      selectionsContainer.append(selectionBadge);
    }
  }
  document
    .getElementById("no-awards")
    .classList.toggle(
      "d-none",
      document.querySelectorAll(".award-card:not(.d-none)").length !== 0,
    );
}

/**
 * handle vote toggling when user clicks vote/unvote button
 * resets current award display and ballot status info
 * closes racer view modal and reopens racers modal if votes are still available
 * @param {*} element button that was clicked to toggle a vote
 * @returns
 */
function toggle_vote(element) {
  let award_ballot = g_ballot[g_awardid];
  let isCurrentlyVoted = award_ballot["votes"].includes(g_racerid);
  let maxVotes = award_ballot["max_votes"];
  let votesCast = award_ballot["votes"].length;

  if (isCurrentlyVoted) {
    // Remove vote
    award_ballot["votes"] = award_ballot["votes"].filter(function (v) {
      return v != g_racerid;
    });
    // set_checkbox(false);
  } else if (votesCast >= maxVotes) {
    // Show warning - ballot is full
    console.log("Full ballot!");
    return;
  } else {
    // Add vote
    award_ballot["votes"].push(g_racerid);
  }

  // Send vote to server
  const formData = new URLSearchParams();
  formData.append("action", "vote.cast");
  formData.append("awardid", g_awardid);
  formData.append("votes", JSON.stringify(award_ballot["votes"]));

  fetch("action.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: formData,
  });

  votesCast = award_ballot["votes"].length;

  // write_racers_headline();
  //find the racer that was just updated and toggle the voted-for class
  var racerCard = document.querySelector(
    ".racer-card[data-racerid='" + g_racerid + "']",
  );
  racerCard.classList.toggle("voted-for", !isCurrentlyVoted);
  set_up_ballot();
  set_ballot_status_info();

  //close the racer_view_modal
  close_modal("#racer_view_modal");

  //if we still have votes available, return to the racers modal
  if (votesCast < maxVotes) {
    show_modal("#racers_modal");
  }
}

/**
 * Sets the ballot status info in the racer view modal
 */
function set_ballot_status_info() {
  let award_ballot = g_ballot[g_awardid];
  let max_votes = award_ballot["max_votes"];
  let votes_cast = award_ballot["votes"].length;

  document.getElementById("racer_view_votes_available").textContent =
    max_votes - votes_cast;
  document.getElementById("racer_view_max_votes").textContent = max_votes;
  document.getElementById("full-ballot-max").textContent = max_votes;

  let isRacerVoted = award_ballot["votes"].includes(g_racerid);
  let isBallotFull = award_ballot["votes"].length >= max_votes;

  //if the racer is voted or the ballot is full, hide the vote button
  document
    .getElementById("racer-vote-btn")
    .classList.toggle("d-none", isRacerVoted || isBallotFull);

  //if the racer is not voted, hide the unvote button
  document
    .getElementById("racer-unvote-btn")
    .classList.toggle("d-none", !isRacerVoted);

  //if the racer is already voted, or the ballot is full, hide the full-ballot message
  document
    .getElementById("full-ballot")
    .classList.toggle("d-none", isRacerVoted || !isBallotFull);
}

function thumbnail_url_for_racerid(racerid) {
  let racerCard = document.querySelector(
    `.racer-card[data-racerid='${racerid}']`,
  );

  let img = racerCard.querySelector("img.racer-photo");
  return img ? img.getAttribute("src") : "img/placeholder-car.png";
}

function photo_url_for_racerid(racerid) {
  let racerCard = document.querySelector(
    `.racer-card[data-racerid='${racerid}']`,
  );
  return racerCard ? racerCard.getAttribute("data-img") : null;
}

function car_number_for_racerid(racerid) {
  let racerCard = document.querySelector(
    `.racer-card[data-racerid='${racerid}']`,
  );
  if (racerCard) {
    let carNumber = racerCard.getAttribute("data-carnumber");
    if (carNumber) {
      return carNumber;
    }
    return "";
  }
  return "";
}

// Bootstrap 5 compatible modal functions
function close_modal(selector) {
  var modalId = selector.replace("#", "");
  var modalElement = document.getElementById(modalId);
  if (modalElement) {
    var modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) modal.hide();
  }
}

function show_modal(selector, callback) {
  var modalId = selector.replace("#", "");
  var modalElement = document.getElementById(modalId);
  if (modalElement) {
    var modal = new bootstrap.Modal(modalElement);
    modal.show();
    if (callback) callback();
  }
}

// Password handling function
function get_ballot() {
  const params = new URLSearchParams();
  params.append("query", "ballot.get");
  params.append("password", document.getElementById("password_input").value);

  fetch("action.php?" + params.toString(), {
    method: "GET",
  })
    .then((response) => response.json())
    .then((data) => {
      var passwordModal = bootstrap.Modal.getInstance(
        document.getElementById("password_modal"),
      );
      var isPasswordModalShowing =
        passwordModal &&
        document.getElementById("password_modal").classList.contains("show");

      if (data.hasOwnProperty("outcome") && data.outcome.summary == "failure") {
        document
          .querySelectorAll("#awards .award-card")
          .forEach((card) => card.classList.add("d-none"));
        if (data.outcome.code == "password") {
          document
            .getElementById("wrong-password")
            .classList.toggle("d-none", !isPasswordModalShowing);
          if (!isPasswordModalShowing) {
            var modal = new bootstrap.Modal(
              document.getElementById("password_modal"),
            );
            modal.show();
          }
        }
      } else {
        g_ballot = data.ballot;
        if (passwordModal) passwordModal.hide();
        set_up_ballot();
      }
    })
    .catch((error) => {
      console.error("Error fetching ballot:", error);
    });
}

// Polling for ballot status changes
document.addEventListener("DOMContentLoaded", function () {
  var balloting_open_or_closed = "";

  // Handle password form submission
  document
    .getElementById("password-form")
    .addEventListener("submit", function (e) {
      e.preventDefault();
      get_ballot();
    });

  // Poll for balloting status changes
  setInterval(function () {
    const params = new URLSearchParams();
    params.append("query", "settings.list");
    params.append("key", "balloting");

    fetch("action.php?" + params.toString(), {
      method: "GET",
    })
      .then((response) => response.json())
      .then((data) => {
        var v =
          (data.hasOwnProperty("settings") ? data.settings.balloting : false) ||
          "closed";
        if (balloting_open_or_closed == "") {
          balloting_open_or_closed = v;
        } else if (balloting_open_or_closed != v) {
          location.reload(true);
        }
      })
      .catch((error) => {
        console.error("Error polling balloting status:", error);
      });
  }, 5000);
});
