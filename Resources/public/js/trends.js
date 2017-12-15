(function () {
  var ranges = {
    YEAR: {
      max: 9
    },
    MONTH: {
      max: 11
    },
    DAY: {
      max: 31,
      increment: 86400
    },
    HOUR: {
      max: 23,
      increment: 3600
    },
    MINUTE: {
      max: 59,
      increment: 60
    }
  };

  var selectElement = document.getElementById('type');
  var spinElement = document.getElementById('type-spin');
  var rangeElement = document.getElementById('date-adjuster');
  var rangeValueElement = document.getElementById('date-adjuster-value');
  var rangeMaxDate;
  var prevIdx;

  selectElement.onchange = selectChange;
  rangeElement.onchange = rangeChange;

  selectChange();

  function selectChange() {
    var idx = selectElement.selectedIndex;
    if (prevIdx === idx) {
      return;
    }
    var value = getSelectedValue();
    if (value) {
      spinElement.style.visibility = 'visible';
      rangeMaxDate = new Date();
      rangeValueElement.innerText = rangeMaxDate.toLocaleString();
      var newRangeMax = ranges[value].max;
      rangeElement.max = newRangeMax;
      rangeElement.value = newRangeMax;
      fetchData(value, rangeMaxDate.toISOString());
    }
  }


  function getSelectedValue() {
    var idx = selectElement.selectedIndex;
    var options = selectElement.getElementsByTagName('OPTION');
    if (idx >= 0) {
      var selectedOption = options[idx];
      return selectedOption.value;
    }
  }

  function rangeChange() {
    var newEnd = calculateRangeLabel();
    var value = getSelectedValue();
    fetchData(value, newEnd.toISOString());
  }

  function calculateRangeLabel() {
    var selectedValue = getSelectedValue();
    var increment = ranges[selectedValue].increment;
    var rangeValue = rangeElement.value;
    var finalDate = new Date();
    finalDate.setTime(rangeMaxDate.getTime());
    var rangeValueInt = parseInt(rangeValue);

    for (var i = parseInt(rangeElement.max); i > rangeValueInt; i--) {
      if (increment) {
        finalDate.setTime(finalDate.getTime() - (increment * 1000));
      }
      else {
        if (selectedValue === 'MONTH') {
          finalDate.setMonth(finalDate.getMonth() - 1);
        }
        if (selectedValue === 'YEAR') {
          finalDate.setYear(finalDate.getFullYear() - 1);
        }
      }
    }
    rangeValueElement.innerText = finalDate.toLocaleString();
    return finalDate;
  }

  function fetchData(type, end) {
    fetch(fetchPath + '?type=' + type + '&end=' + end, {credentials: 'include'}).then(function (response) {
      if (response.status === 200) {
        response.json().then(function (data) {
          if (getSelectedValue() === type) {
            renderTrends(type, data);
            spinElement.style.visibility = 'hidden';
          }
        });
      }
    });
  }

  function convertDates(type, dateList) {
    var newDates = [];
    for (var i = 0, len = dateList.length; i < len; i++) {
      var parsedDate = Date.parse(dateList[i]);
      if (typeof(parsedDate) === 'number') {
        var realDate = new Date(parsedDate);
        switch (type) {
          case 'YEAR':
            newDates.push(realDate.getFullYear());
            break;
          case'MONTH':
            newDates.push(realDate.getFullYear() + '-' + (realDate.getMonth() + 1));
            break;
          case 'DAY':
            newDates.push(realDate.getFullYear() + '-' + (realDate.getMonth() + 1) + '-' + realDate.getDate());
            break;
          case 'HOUR':
            newDates.push(realDate.getFullYear() + '-' + (realDate.getMonth() + 1) + '-' + realDate.getDate() + ' ' + realDate.getHours());
            break;
        }
      }
    }
    return newDates;
  }

  function renderTrends(type, data) {
    document.getElementById('range-value-container').style.display = 'block';
    document.getElementById('range-container').style.display = 'block';
    var label = type.toString().toLowerCase();
    var dates = convertDates(type, data['timings_dates_rfc3339']);
    var datasets = [];
    var curLabel;
    var curColor;
    var timingsData;
    for (var state in states) {
      if (states.hasOwnProperty(state)) {
        curLabel = states[state].label;
        curColor = states[state].color;
        timingsData = data['timings_data_' + state];
        datasets.push(
          {
            label: curLabel,
            backgroundColor: curColor,
            borderColor: curColor,
            data: timingsData,
            fill: false
          }
        );
      }
    }
    var red = 'rgb(255, 99, 132)';
    var canvasEle = document.getElementById('trends');
    var chart = new Chart(canvasEle, {
      type: 'line',
      data: {
        labels: dates,
        datasets: datasets
      },
      options: {
        responsive: true,
        title: {
          display: true,
          text: 'Job Timings'
        },
        tooltips: {
          mode: 'index',
          intersect: true
        },
        scales: {
          xAxes: [
            {
              display: true,
              scaleLabel: {
                display: true,
                labelString: label
              }
            }
          ],
          yAxes: [{
            ticks: {
              beginAtZero: true
            },
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Count'
            }
          }]
        }
      }
    });
  }
})();