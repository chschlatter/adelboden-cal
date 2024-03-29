<?php

use CalApi\IAM;

$container = require __DIR__ . '/../bootstrap.php';

$iam = $container->get(IAM::class);
if (null == ($username = $iam->cookieAuth())) {
  header('Location: /login.php');
}

$SERVER_VARS = array('username' => $username);

?>
<!DOCTYPE html>
<html lang='en'>
  <head>
    <meta charset='utf-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendar</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css' rel='stylesheet'>

   
  </head>
  <body>
    <div class="container">

      <div class="page-header text-center">
        <h1>Calendar</h1>
        <h3>User: <?php echo $SERVER_VARS['username']; ?></h3>
      </div>

      <hr>

      <div id="calendar"></div>

    </div>

    <!-- Modal -->
    <div class="modal fade" id="event-modal" tabindex="-1" role="dialog" aria-labelledby="event-modal-label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="event-modal-title">Reservation</h5>
            <button type="button" class="close" data-bs-dismiss="modal" data-bs-target="#event-modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form name="add-event-modal" method="post" id="add-event-form-modal">
            <div class="modal-body" style="min-height: 150px;">
              <div class="row mb-3">
                <label for="start" class="col-sm-1 col-form-label">Von:</label>
                <div class="col-sm-5">
                  <input type="date" class="form-control" name="start" id="input-start-date">
                </div>
            
                <label for="end" class="col-sm-1 col-form-label">Bis:</label>
                <div class="col-sm-5">
                  <input type="date" class="form-control" name="end" id="input-end-date">
                </div>
              </div>

              <div class="row d-none" id="row-select-username">
                <div class="col-sm-12">
                  <label for="select-username">Username</label>
                  <select class="form-control" name="username" id="select-username">
                  </select>
                </div>
              </div>

              <div class="row">
                <p class="text-danger" id="error-msg"></p>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-target="#event-modal" aria-label="Close">Close</button>
              <button type="button" class="btn btn-danger d-none" id="delete-btn">Delete</button>
              <button type="button" class="btn btn-primary" id="submit-btn">Save changes</button>
            </div>
            <input type="hidden" id="event-id" name="id" value="" />
            <input type="hidden" id="event-title" name="title" value="" />
          </form>
        </div>
      </div>
    </div>

    <script>
      var SERVER_VARS = JSON.parse('<?php echo json_encode($SERVER_VARS); ?>');
    </script>

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.5/index.global.min.js'></script>

    <script src="https://code.jquery.com/jquery-3.6.3.min.js" integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU=" crossorigin="anonymous"></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/bootstrap5@6.1.5/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script src="cal.js"></script>

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>
  </body>
</html>