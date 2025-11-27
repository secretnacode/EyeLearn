"""
Simplified Eye Tracking API - Data Storage Only
Client-side JavaScript handles all camera/tracking logic
This API just stores the tracking data in the database
"""

from flask import Flask, jsonify, request
from flask_cors import CORS
import logging
import json
from datetime import datetime
import os
import mysql.connector
from mysql.connector import Error

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

# Database connection using same environment variables as PHP
def get_db_connection():
    """Create database connection using environment variables"""
    try:
        connection = mysql.connector.connect(
            host=os.getenv('MYSQLHOST', 'tramway.proxy.rlwy.net'),
            user=os.getenv('MYSQLUSER', 'root'),
            password=os.getenv('MYSQLPASSWORD', 'niCcpkrZKKhLDhXeTbcbhIYSFJBfNibP'),
            database=os.getenv('MYSQLDATABASE', 'railway'),
            port=int(os.getenv('MYSQLPORT', 10241))
        )
        return connection
    except Error as e:
        logger.error(f"❌ Database connection error: {e}")
        return None

# Create eye_tracking_sessions table if it doesn't exist
def init_database():
    """Initialize database table for eye tracking"""
    try:
        conn = get_db_connection()
        if conn:
            cursor = conn.cursor()
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS eye_tracking_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    module_id INT NOT NULL,
                    section_id VARCHAR(50),
                    focused_time INT NOT NULL,
                    unfocused_time INT NOT NULL,
                    total_time INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_module (user_id, module_id)
                )
            """)
            conn.commit()
            cursor.close()
            conn.close()
            logger.info("✅ Database table initialized")
            return True
        else:
            logger.warning("⚠️ Database connection failed - running without database")
            return False
    except Exception as e:
        logger.error(f"❌ Error initializing database: {e}")
        logger.warning("⚠️ Service will continue without database")
        return False

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    # Test database connection
    conn = get_db_connection()
    db_status = 'connected' if conn else 'disconnected'
    if conn:
        conn.close()
    
    return jsonify({
        'success': True,
        'service': 'Eye Tracking Data API',
        'version': '3.0-client-side',
        'mode': 'data-storage-only',
        'database': db_status,
        'timestamp': datetime.now().isoformat()
    })

@app.route('/api/save_tracking', methods=['POST'])
def save_tracking():
    """Save tracking data from client-side JavaScript to MySQL database"""
    try:
        data = request.json
        
        # Validate required fields
        required_fields = ['user_id', 'module_id', 'focused_time', 'unfocused_time', 'total_time']
        for field in required_fields:
            if field not in data:
                return jsonify({
                    'success': False,
                    'error': f'Missing required field: {field}'
                }), 400
        
        # Connect to database
        conn = get_db_connection()
        if not conn:
            return jsonify({
                'success': False,
                'error': 'Database connection failed'
            }), 500
        
        try:
            cursor = conn.cursor()
            
            # Insert tracking data
            query = """
                INSERT INTO eye_tracking_sessions 
                (user_id, module_id, section_id, focused_time, unfocused_time, total_time)
                VALUES (%s, %s, %s, %s, %s, %s)
            """
            
            values = (
                data['user_id'],
                data['module_id'],
                data.get('section_id'),  # Optional
                data['focused_time'],
                data['unfocused_time'],
                data['total_time']
            )
            
            cursor.execute(query, values)
            conn.commit()
            session_id = cursor.lastrowid
            
            cursor.close()
            conn.close()
            
            logger.info(f"✅ Saved tracking data: User {data['user_id']}, Module {data['module_id']}, "
                       f"Focused: {data['focused_time']}s, Total: {data['total_time']}s, ID: {session_id}")
            
            return jsonify({
                'success': True,
                'message': 'Tracking data saved successfully',
                'session_id': session_id
            })
            
        except Error as e:
            logger.error(f"❌ Database error: {e}")
            if conn:
                conn.close()
            return jsonify({
                'success': False,
                'error': f'Database error: {str(e)}'
            }), 500
        
    except Exception as e:
        logger.error(f"❌ Error saving tracking data: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/api/get_tracking_stats', methods=['GET'])
def get_tracking_stats():
    """Get tracking statistics for a user/module from MySQL database"""
    try:
        user_id = request.args.get('user_id')
        module_id = request.args.get('module_id')
        
        conn = get_db_connection()
        if not conn:
            return jsonify({
                'success': False,
                'error': 'Database connection failed'
            }), 500
        
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Build query with optional filters
            query = "SELECT * FROM eye_tracking_sessions WHERE 1=1"
            params = []
            
            if user_id:
                query += " AND user_id = %s"
                params.append(user_id)
            
            if module_id:
                query += " AND module_id = %s"
                params.append(module_id)
            
            query += " ORDER BY created_at DESC"
            
            cursor.execute(query, params)
            sessions = cursor.fetchall()
            
            # Calculate totals
            total_focused = sum(s.get('focused_time', 0) for s in sessions)
            total_unfocused = sum(s.get('unfocused_time', 0) for s in sessions)
            total_time = sum(s.get('total_time', 0) for s in sessions)
            
            focus_rate = 0
            if total_time > 0:
                focus_rate = round((total_focused / total_time) * 100, 2)
            
            cursor.close()
            conn.close()
            
            return jsonify({
                'success': True,
                'stats': {
                    'total_sessions': len(sessions),
                    'total_focused_time': total_focused,
                    'total_unfocused_time': total_unfocused,
                    'total_time': total_time,
                    'focus_rate': focus_rate,
                    'sessions': sessions
                }
            })
            
        except Error as e:
            logger.error(f"❌ Database error: {e}")
            if conn:
                conn.close()
            return jsonify({
                'success': False,
                'error': f'Database error: {str(e)}'
            }), 500
        
    except Exception as e:
        logger.error(f"❌ Error getting tracking stats: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


if __name__ == '__main__':
    import os
    
    # Initialize database table
    init_database()
    
    host = os.getenv('EYE_TRACKING_HOST', '0.0.0.0')
    port = int(os.getenv('EYE_TRACKING_PORT', 5000))
    
    logger.info("="* 60)
    logger.info("🚀 Eye Tracking Data API Starting...")
    logger.info(f"📍 Listening on {host}:{port}")
    logger.info("📌 Mode: Client-Side Tracking (Data Storage Only)")
    logger.info("📹 Camera access handled by browser JavaScript")
    logger.info("💾 Database: MySQL (using environment variables)")
    logger.info("="*60)
    
    app.run(host=host, port=port, debug=False, threaded=True)

