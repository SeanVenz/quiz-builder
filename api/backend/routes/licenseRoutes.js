const express = require('express');
const router = express.Router();
const {
  generateLicense,
  validateLicense,
  getLicenseDetails,
  deactivateLicense
} = require('../controllers/licenseController');
const authenticateToken = require('../middleware/authMiddleware');

router.post('/generate', authenticateToken, generateLicense);

router.post('/validate', validateLicense);

router.get('/:licenseKey', authenticateToken, getLicenseDetails);

router.put('/:licenseKey/deactivate', deactivateLicense);

module.exports = router;
