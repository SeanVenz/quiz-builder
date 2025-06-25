const License = require('../models/License');
const {Sequelize} = require('sequelize');
const User = require('../models/User');

const defineAssociations = () => {

    User.hasMany(License, {
        foreignKey: 'userId',
        as: 'userID'
    });

    License.belongsTo(User, {
        foreignKey: 'userId',
        as: 'userGenerated',
    })
};

module.exports = defineAssociations