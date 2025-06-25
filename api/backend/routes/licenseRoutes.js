const express = require('express');
const router = express.Router();
const {
  generateLicense,
  validateLicense,
  getLicenseDetails,
  deactivateLicense
} = require('../controllers/licenseController');

// POST /api/licenses/generate - Generate a new license key
router.post('/generate', generateLicense);

// POST /api/licenses/validate - Validate a license key (main endpoint for WordPress)
router.post('/validate', validateLicense);

// GET /api/licenses/:licenseKey - Get license details
router.get('/:licenseKey', getLicenseDetails);

// PUT /api/licenses/:licenseKey/deactivate - Deactivate a license
router.put('/:licenseKey/deactivate', deactivateLicense);

module.exports = router;
