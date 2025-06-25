const { DataTypes } = require('sequelize');
const sequelize = require('../database/sequelize');
const crypto = require('crypto');

const License = sequelize.define('License', {
  id: {
    type: DataTypes.INTEGER,
    primaryKey: true,
    autoIncrement: true
  },
  licenseKey: {
    type: DataTypes.STRING(64),
    allowNull: false,
    unique: true,
    validate: {
      len: [32, 64]
    }
  },
  isActive: {
    type: DataTypes.BOOLEAN,
    defaultValue: true
  },
  features: {
    type: DataTypes.JSON,
    defaultValue: ['premium_templates', 'advanced_analytics', 'custom_branding']
  },
  lastValidated: {
    type: DataTypes.DATE,
    allowNull: true
  },
  validationCount: {
    type: DataTypes.INTEGER,
    defaultValue: 0
  },
  siteUrl: {
    type: DataTypes.STRING(255),
    allowNull: true
  }
}, {
  tableName: 'licenses',
  timestamps: true, // This adds createdAt and updatedAt
  indexes: [
    {
      unique: true,
      fields: ['licenseKey']
    }
  ]
});

// Static method to generate a secure license key
License.generateLicenseKey = function() {
  // Generate a 32-character alphanumeric license key
  const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let result = '';
  
  // Generate 8 groups of 4 characters separated by hyphens
  for (let group = 0; group < 8; group++) {
    if (group > 0) result += '-';
    for (let i = 0; i < 4; i++) {
      result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
  }
  
  return result;
};

// Instance method to validate license
License.prototype.validateLicense = async function() {
  if (!this.isActive) {
    return { valid: false, message: 'License is inactive' };
  }
  
  // Update validation tracking
  this.lastValidated = new Date();
  this.validationCount += 1;
  await this.save();
  
  return {
    valid: true,
    features: this.features,
    message: 'License is valid'
  };
};

module.exports = License;