(function () {
  var inputs = document.getElementsByTagName("input");
  for (var i = 0 ; i < inputs.length ; i++) {
    if (inputs[i].className === 'worker-method-tag') {
      setup(inputs[i].value)
    }
  }

  function setup(tag) {
    var workerNameElement = document.getElementById('worker-name-' + tag);
    var methodElement = document.getElementById('method-' + tag);
    var originalMethodHtml = methodElement.innerHTML;
    var archiveButton = document.getElementById('worker-method-action-' + tag);
    var progressElement = document.getElementById('worker-method-progress-' + tag);

    archiveButton.addEventListener('click', promptArchive);
    workerNameElement.addEventListener('change', workerNameChange);

    function workerNameChange() {
      var workerName = getSelected(workerNameElement);
      if (workerName) {
        methodElement.innerHTML = generateOptions(window['workerMethods_'+tag][workerName]);
        return;
      }
      methodElement.innerHTML = originalMethodHtml;
    }

    function getSelected(element) {
      var idx = element.selectedIndex;
      if (idx > 0) {
        var options = element.getElementsByTagName('option');
        var optionElement = options[idx];
        if (optionElement) {
          return optionElement.value;
        }
      }
      return null
    }

    function generateOptions(list) {
      var optionHtml = '<option value="">Any</option>';
      for (var i = 0, len = list.length; i < len; i++) {
        var method = list[i];
        optionHtml += '<option value"' + method + '">' + method + '</option>';
      }
      return optionHtml;
    }

    function promptArchive() {
      var workerName = getSelected(workerNameElement);
      var method = getSelected(methodElement);
      var message = "Are you sure?\n\n" + window['promptMessage_'+tag];
      if (workerName) {
        message += " of worker '" + workerName + "'";
      }
      if (method) {
        message += ", method '" + method + "'";
      }
      message += ".";
      if (confirm(message)) {
        archiveJobs();
      }
    }

    function archiveJobs() {
      var workerName = getSelected(workerNameElement);
      var method = getSelected(methodElement);
      var formData = new FormData;
      var spinner = archiveButton.getElementsByTagName('i');
      spinner[0].classList.remove('dtc-grid-hidden');

      // disable all the buttons
      var buttons = document.getElementsByTagName('button');
      for (var i = 0, len = buttons.length; i < len; i++) {
        buttons[i].disabled = true;
      }

      var pagination = document.getElementsByClassName('dataTables_paginate');
      pagination[0].style.visibility = 'hidden';

      if (workerName) {
        formData.append('workerName', workerName);
      }
      if (method) {
        formData.append('method', method);
      }

      progressElement.max = "10";
      progressElement.value = "0";
      progressElement.style.display = 'inline';

      fetch(window['fetchPath_' + tag], {
        credentials: 'include',
        method: 'post',
        body: formData
      }).then(function (response) {
        processResponse(response.body.getReader());
      });


      function processResponse(reader) {
        reader.read().then(function processResult(result) {
          if (result.done) {
            progressElement.value = progressElement.max;
            window.location.reload();
            return;
          }
          var progress = (new TextDecoder('utf-8')).decode(result.value);
          var lines = progress.split(/\n/);

          for (var i = 0, len = lines.length; i < len; i++) {
            if (lines[i]) {
              var json = JSON.parse(lines[i]);
              console.log("received", json);
              if (json) {
                if (json.total !== undefined) {
                  if (json.total === 0) {
                    progressElement.value = progressElement.max;
                  }
                  else {
                    progressElement.max = json.total.toString();
                  }
                }
                else if (json.count) {
                  progressElement.value = json.count.toString();
                }
              }
            }
          }
          processResponse(reader);
        });
      }
    }
  }
})();
