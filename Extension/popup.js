document.getElementById('run').addEventListener('click', async () => {
  const resDiv = document.getElementById('res');
  resDiv.className = '';
  resDiv.style.display = 'block';
  resDiv.innerText = 'Processing request...';
  try {
    const response = await fetch('http://127.0.0.1:5001/api/embed', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer stegops_internal_secret_2025'
      },
      body: JSON.stringify({
        carrier_path: document.getElementById('carrier').value,
        output_dir: document.getElementById('outdir').value,
        payload: document.getElementById('text').value,
        payload_type: 'text',
        steg_method: 'dct',
        file_type: 'image',
        op_id: Date.now()
      })
    });
    const data = await response.json();
    if (data.success) {
      resDiv.className = 'success';
      resDiv.innerText = `Success!\nSaved as: ${data.output_filename}`;
    } else {
      resDiv.className = 'error';
      resDiv.innerText = `Error: ${data.error}`;
    }
  } catch (err) {
    resDiv.className = 'error';
    resDiv.innerText = `Connection Failed: ${err.message}`;
  }
});