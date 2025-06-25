const sequelize = require('./sequelize');
const License = require('../models/License')

const initializeDatabase = async () => {
    try{
        await sequelize.authenticate();
        console.log('Database connection has been established successfully.');

        // defineAssociations();

        await sequelize.sync({alter: false});
        console.log('Database synchronized successfully.');
    } catch(error){
        console.log('Unable to connect to the database:', error);
    }
}

module.exports = initializeDatabase;