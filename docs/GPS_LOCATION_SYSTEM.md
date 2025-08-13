# GPS Location System

## Overview

The GPS Location System allows technicians to share their real-time GPS location with administrators, enabling real-time tracking on the interactive map. This system provides accurate location data instead of relying solely on address geocoding.

## Features

### For Technicians
- **Real-time GPS tracking** using browser geolocation API
- **Automatic location updates** every 5 minutes (configurable)
- **Manual location updates** on demand
- **Location sharing toggle** to enable/disable tracking
- **Status indicators** showing tracking state and last update time
- **Privacy controls** to manage location sharing preferences

### For Administrators
- **Real-time technician locations** on the interactive map
- **GPS coordinates** instead of approximate address locations
- **Location timestamps** showing when location was last updated
- **Fallback to address geocoding** when GPS is unavailable
- **Visual indicators** distinguishing GPS vs. address-based locations

## How It Works

### 1. Location Collection
- Technicians enable location sharing in their profile or dashboard
- Browser requests GPS permission from the user
- GPS coordinates are collected using the HTML5 Geolocation API
- Location data is sent to the server via AJAX

### 2. Location Storage
- GPS coordinates are stored in the `users` table:
  - `current_latitude` (decimal 10,8)
  - `current_longitude` (decimal 11,8)
  - `location_updated_at` (timestamp)
  - `location_sharing_enabled` (boolean)

### 3. Location Display
- Map component checks for GPS coordinates first
- Falls back to address geocoding if GPS unavailable
- Shows location source (GPS vs. Address) in popups
- Displays timestamp information for location freshness

## Database Schema

```sql
ALTER TABLE users ADD COLUMN current_latitude DECIMAL(10,8) NULL;
ALTER TABLE users ADD COLUMN current_longitude DECIMAL(11,8) NULL;
ALTER TABLE users ADD COLUMN location_updated_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN location_sharing_enabled BOOLEAN DEFAULT TRUE;
```

## API Endpoints

### Update Location
```
POST /gps/update-location
Content-Type: application/json
X-CSRF-TOKEN: {token}

{
    "latitude": 33.7490,
    "longitude": -84.3880
}
```

### Get Location
```
GET /gps/get-location
Authorization: Bearer {token}
```

### Toggle Location Sharing
```
POST /gps/toggle-sharing
Content-Type: application/json
X-CSRF-TOKEN: {token}

{
    "enabled": true
}
```

## Components

### GPS Location Tracker (`<x-gps-location-tracker>`)
- **Location**: Profile edit page and technician dashboard
- **Features**: 
  - GPS permission management
  - Real-time location tracking
  - Status display
  - Manual update controls
- **Props**:
  - `update-interval`: Milliseconds between updates (default: 300000 = 5 minutes)
  - `show-status`: Whether to show status information (default: true)

### Interactive Map (`<x-location-map>`)
- **Enhanced with**: GPS coordinate support
- **Features**:
  - GPS coordinates take priority over address geocoding
  - Location source indicators
  - Timestamp information
  - Fallback to address geocoding

## User Experience

### Technician Workflow
1. **Enable Location Sharing**: Toggle switch in profile or dashboard
2. **Grant Permission**: Allow browser to access GPS location
3. **Automatic Updates**: Location updates every 5 minutes
4. **Manual Updates**: Click "Update Location Now" for immediate update
5. **Privacy Control**: Toggle location sharing on/off as needed

### Administrator Workflow
1. **View Technicians**: See technician list with location status
2. **Interactive Map**: View real-time technician locations
3. **Location Details**: Click markers for detailed information
4. **Location Source**: Distinguish between GPS and address-based locations

## Security & Privacy

### Data Protection
- **HTTPS Required**: All location data transmitted over secure connections
- **User Consent**: Explicit permission required before location sharing
- **Data Retention**: Location data stored only while sharing is enabled
- **Access Control**: Only authenticated technicians can update their own location

### Privacy Controls
- **Opt-in Only**: Location sharing disabled by default
- **User Control**: Technicians can disable sharing at any time
- **Granular Control**: Toggle location sharing on/off
- **Data Ownership**: Technicians control their own location data

## Technical Implementation

### Frontend (JavaScript)
- **Geolocation API**: HTML5 standard for GPS access
- **Permission API**: Modern browser permission management
- **Watch Position**: Continuous location monitoring
- **AJAX Communication**: RESTful API calls to backend

### Backend (Laravel)
- **Validation**: Coordinate range validation (-90 to 90, -180 to 180)
- **Authentication**: Middleware-based access control
- **Database**: Efficient storage with proper indexing
- **Logging**: Comprehensive activity logging

### Performance
- **Caching**: Location data cached appropriately
- **Efficient Queries**: Optimized database queries
- **Minimal Overhead**: Lightweight implementation
- **Scalable**: Designed for multiple concurrent users

## Configuration

### Update Intervals
- **Default**: 10 seconds (10,000 milliseconds)
- **Configurable**: Per component instance
- **Real-time**: Immediate updates on manual requests
- **High Frequency**: Provides near real-time location tracking

### Accuracy Settings
- **High Accuracy**: GPS preferred over network location
- **Timeout**: 10 seconds for location requests
- **Maximum Age**: 5 minutes for cached locations
- **Fallback**: Network-based location if GPS unavailable

## Troubleshooting

### Common Issues

#### Location Not Updating
- Check browser permissions
- Verify GPS is enabled on device
- Check internet connection
- Review browser console for errors

#### Permission Denied
- Clear browser permissions
- Check device GPS settings
- Try different browser
- Check HTTPS requirement

#### Map Not Showing Technicians
- Verify location sharing is enabled
- Check GPS coordinates in database
- Review map component logs
- Ensure technician has location data

### Debug Information
- **Browser Console**: Detailed error messages
- **Network Tab**: API call status
- **Database**: Direct location data inspection
- **Server Logs**: Backend error tracking

## Future Enhancements

### Planned Features
- **Geofencing**: Automatic notifications when entering/leaving areas
- **Route Tracking**: Historical location path visualization
- **Offline Support**: Location caching for poor connectivity
- **Battery Optimization**: Smart update intervals based on device state

### Integration Opportunities
- **Mobile Apps**: Native GPS integration
- **IoT Devices**: Hardware-based location tracking
- **Third-party Services**: Integration with fleet management systems
- **Analytics**: Location-based performance metrics

## Best Practices

### For Technicians
- **Enable Location Sharing**: Only when needed for work
- **Regular Updates**: Keep location data current
- **Privacy Awareness**: Understand what data is shared
- **Battery Management**: Monitor device battery usage

### For Administrators
- **Respect Privacy**: Use location data responsibly
- **Clear Communication**: Explain location tracking purpose
- **Data Retention**: Implement appropriate data cleanup policies
- **Security**: Ensure secure access to location data

### For Developers
- **Error Handling**: Graceful fallbacks for all scenarios
- **Performance**: Optimize for mobile devices
- **Security**: Validate all location data
- **Testing**: Test across different devices and browsers

## Support

### Documentation
- This document provides comprehensive system overview
- Code comments explain implementation details
- API documentation available in codebase

### Troubleshooting
- Check browser console for JavaScript errors
- Review server logs for backend issues
- Verify database schema and data integrity
- Test with different devices and browsers

### Updates
- System improvements documented in changelog
- Security updates communicated promptly
- Feature requests tracked in issue system
- Regular maintenance and optimization
