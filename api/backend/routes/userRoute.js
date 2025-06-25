const express = require('express');
const router = express.Router();
const userController = require('../controllers/userController');

router.get('/' ,userController.getAllUsers);
router.post('/register', userController.createUser);
router.post('/login', userController.loginUser);
router.get('/me', userController.getCurrentUser);
router.post('/logout', userController.logoutUser);

module.exports = router;