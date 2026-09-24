function editForest(data) {
  document.getElementById('forest_id').value = data.forest_id;
  document.getElementById('forest_name').value = data.forest_name;
  document.getElementById('forest_code').value = data.forest_code || '';
  document.getElementById('location').value = data.location;
  document.getElementById('latitude').value = data.latitude || '';
  document.getElementById('longitude').value = data.longitude || '';
  document.getElementById('area_sq_km').value = data.area_sq_km;
  document.getElementById('ecosystem_type').value = data.ecosystem_type;
  document.getElementById('region').value = data.region || '';
  document.getElementById('established_year').value = data.established_year || '';
  document.getElementById('description').value = data.description || '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function clearForestForm() {
  document.getElementById('forest_id').value = '';
  ['forest_name','forest_code','location','latitude','longitude','area_sq_km','ecosystem_type','region','established_year','description'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = '';
  });
}
