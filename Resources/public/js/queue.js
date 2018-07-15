(function () {
  var inputs = document.getElementsByTagName("input");
  for (var i = 0 ; i < inputs.length ; i++) {
    if (inputs[i].className === 'queue-action-tag') {
      setup(inputs[i].value)
    }
  }

  function setup(tag) {
    var workerNameElement = document.getElementById('worker-name-' + tag);
    var olderThanAmountElement = document.getElementById('older-than-amount-' + tag);
    var olderThanTypeElement = document.getElementById('older-than-type-' + tag);
    var methodElement = document.getElementById('method-' + tag);
    var originalMethodHtml = methodElement.innerHTML;
    var actionButton = document.getElementById('queue-action-' + tag);
    var progressElement = document.getElementById('queue-action-progress-' + tag);
    var id = window['id_'+tag];
    var buttonElement = document.getElementsByClassName('refresh-' + id)[0];
    actionButton.addEventListener('click', promptArchive);
    if (workerNameElement) {
      workerNameElement.addEventListener('change', workerNameChange);
    }

    function workerNameChange() {
      var workerName = getSelected(workerNameElement);
      if (workerName) {
        methodElement.innerHTML = generateOptions(window['workerMethods_'+tag][workerName]);
        return;
      }
      methodElement.innerHTML = originalMethodHtml;
    }

    function getSelected(element) {
      if (!element) {
        return null;
      }

      var idx = element.selectedIndex;
      if (idx > 0) {
        var options = element.getElementsByTagName('option');
        var optionElement = options[idx];
        if (optionElement) {
          return optionElement.value;
        }
      }
      return null;
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
        process();
      }
    }

    var disabledButtons = [];
    function disableButtons(buttons) {
      for (var i = 0, len = buttons.length; i < len; i++) {
        if (!buttons[i].disabled) {
          buttons[i].disabled = true;
          disabledButtons.push(buttons[i]);
        }
      }
    }
    function disableGrid() {
      var tableEle = document.getElementById(id + '_wrapper');
      var buttons = tableEle.getElementsByTagName('button');
      disableButtons(buttons);
      var areaEles = document.getElementsByClassName('worker-method-' + tag);
      var buttons2 = areaEles[0].getElementsByTagName('button');
      disableButtons(buttons2);
      var pagination = tableEle.getElementsByClassName('dataTables_paginate');
      pagination[0].style.visibility = 'hidden';
    }

    function enableGrid() {
      for (var i = 0, len = disabledButtons.length; i < len; i++) {
        if (disabledButtons[i].disabled) {
          disabledButtons[i].disabled = false;
        }
      }
      disabledButtons = [];
      var tableEle = document.getElementById(id + '_wrapper');
      var pagination = tableEle.getElementsByClassName('dataTables_paginate');
      pagination[0].style.visibility = 'visible';
    }



    function process() {
      var workerName = getSelected(workerNameElement);
      var method = getSelected(methodElement);
      var olderThanAmount = olderThanAmount ? olderThanAmount.value : null;
      var olderThanType = getSelected(olderThanType);
      var formData = new FormData;
      var spinner = actionButton.getElementsByTagName('i');
      spinner[0].classList.remove('dtc-grid-hidden');

      // disable all the buttons
      disableGrid();

      if (workerName) {
        formData.append('workerName', workerName);
      }
      if (method) {
        formData.append('method', method);
      }
      if (olderThanAmount) {
        formData.append('olderThanAmount', olderThanAmount);
      }
      if (olderThanType) {
        formData.append('olderThanType', olderThanType);
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
            enableGrid();
            spinner[0].classList.add('hidden');
            progressElement.style.display = 'none';

            dtc_grid_refresh(buttonElement);
            console.log('done');
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
