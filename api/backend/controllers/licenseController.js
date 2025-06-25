const License = require('../models/License');

// Generate a new license key (for internal use/testing)
const generateLicense = async (req, res) => {
  try {
    const licenseKey = License.generateLicenseKey();
    
    const license = await License.create({
      licenseKey: licenseKey,
      features: ['premium_templates', 'advanced_analytics', 'custom_branding']
    });

    res.status(201).json({
      success: true,
      message: 'License key generated successfully',
      data: {
        licenseKey: license.licenseKey,
        features: license.features,
        createdAt: license.createdAt
      }
    });
  } catch (error) {
    console.error('Error generating license:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to generate license key',
      error: error.message
    });
  }
};

// Validate a license key (this will be called from WordPress)
const validateLicense = async (req, res) => {
  try {
    const { licenseKey, siteUrl } = req.body;

    if (!licenseKey) {
      return res.status(400).json({
        success: false,
        message: 'License key is required'
      });
    }

    // Find the license
    const license = await License.findOne({
      where: { licenseKey: licenseKey }
    });

    if (!license) {
      return res.status(404).json({
        success: false,
        message: 'Invalid license key'
      });
    }

    // Validate the license
    const validationResult = await license.validateLicense();

    if (!validationResult.valid) {
      return res.status(403).json({
        success: false,
        message: validationResult.message
      });
    }

    // Optional: Update site URL if provided
    if (siteUrl && license.siteUrl !== siteUrl) {
      license.siteUrl = siteUrl;
      await license.save();
    }

    res.json({
      success: true,
      message: 'License is valid',
      data: {
        features: validationResult.features,
        validationCount: license.validationCount,
        lastValidated: license.lastValidated
      }
    });

  } catch (error) {
    console.error('Error validating license:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to validate license',
      error: error.message
    });
  }
};

// Get license details (for testing/admin purposes)
const getLicenseDetails = async (req, res) => {
  try {
    const { licenseKey } = req.params;

    const license = await License.findOne({
      where: { licenseKey: licenseKey },
      attributes: ['licenseKey', 'isActive', 'features', 'validationCount', 'lastValidated', 'siteUrl', 'createdAt']
    });

    if (!license) {
      return res.status(404).json({
        success: false,
        message: 'License not found'
      });
    }

    res.json({
      success: true,
      data: license
    });

  } catch (error) {
    console.error('Error fetching license details:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch license details',
      error: error.message
    });
  }
};

// Deactivate a license (for testing/admin purposes)
const deactivateLicense = async (req, res) => {
  try {
    const { licenseKey } = req.params;

    const license = await License.findOne({
      where: { licenseKey: licenseKey }
    });

    if (!license) {
      return res.status(404).json({
        success: false,
        message: 'License not found'
      });
    }

    license.isActive = false;
    await license.save();

    res.json({
      success: true,
      message: 'License deactivated successfully'
    });

  } catch (error) {
    console.error('Error deactivating license:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to deactivate license',
      error: error.message
    });
  }
};

module.exports = {
  generateLicense,
  validateLicense,
  getLicenseDetails,
  deactivateLicense
};
