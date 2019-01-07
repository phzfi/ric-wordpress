function getQueryString(obj) {
  var queryStr = '';
  var first = true;

  for(var key in obj) {
    queryStr += (first ? '?': '&') + encodeURIComponent(key) + '=' + encodeURIComponent(obj[key])
    first = false;
  }

  return queryStr;
};

function handleSingleImage(img) {
  let queryObject = {};
  let src = img.getAttribute("data-src")
  let width = img.getAttribute("data-width");

  if(src !== "") {
    return;
  }

  if (width === null || width > screen.width) {
    queryObject.width = screen.width;
  } else {
    queryObject.width = width;
  }

  img.src = img.getAttribute("data-src") + getQueryString(queryObject);
};

function processAllImages() {
  // images is 'HTMLCollection'
  var images = document.getElementsByTagName('img');

  var length = images.length;
  for(var i = 0; i<length ; i++) {
    handleSingleImage(images[i]);
  }
};
// RIC private tasks end

window.onload = function() {
  processAllImages();
}


