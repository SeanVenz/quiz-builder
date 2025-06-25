const express = require('express')
const app = express();
var cors = require('cors');
const dotenv = require('dotenv');
dotenv.config();

const PORT = process.env.PORT || 3000;

const initializeDatabase = require('./database/dbFunctions');

const cookieParser = require('cookie-parser');

// Import routes
const licenseRoutes = require('./routes/licenseRoutes');

app.use(express.json());
app.use(cookieParser());
app.use(cors({
  origin:process.env.FRONTEND_URL,
  credentials:true
}));

// Setup routes
app.use('/api/licenses', licenseRoutes);

require('./database/dbFunctions');

initializeDatabase().then(() => {
  app.listen(PORT, () => {
  console.log(`Server is running on http://localhost:${PORT}`);
});
})