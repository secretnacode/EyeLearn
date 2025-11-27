<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | AI-Enhanced E-Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-background">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white shadow-lg fixed left-0 top-0 h-full transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50">
            <!-- Sidebar content -->
        </div>

        <!-- Main Content -->
        <main class="flex-1 md:ml-64 p-5">
            <div class="container mx-auto">
                <h1 class="text-3xl font-bold mb-6 text-primary">Student Profile</h1>
                
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Profile Details -->
                    <div class="md:col-span-1 bg-white p-6 rounded-lg shadow-md text-center">
                        <img src="/api/placeholder/200/200" alt="Profile" class="w-32 h-32 rounded-full mx-auto mb-4">
                        <h2 class="text-xl font-semibold">Emma Johnson</h2>
                        <p class="text-gray-600">Computer Science Student</p>
                        
                        <div class="mt-6">
                            <h3 class="font-semibold mb-2">Learning Metrics</h3>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-sm text-gray-600">Course Completion</span>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-primary h-2.5 rounded-full" style="width: 65%"></div>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-600">Eye-Tracking Performance</span>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-secondary h-2.5 rounded-full" style="width: 75%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information -->
                    <div class="md:col-span-2 bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-2xl font-semibold mb-6">Personal Information</h2>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Full Name</label>
                                <input type="text" value="Emma Johnson" class="w-full p-2 border rounded" disabled>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Email</label>
                                <input type="email" value="emma.johnson@example.com" class="w-full p-2 border rounded" disabled>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Date of Birth</label>
                                <input type="text" value="15 September 2000" class="w-full p-2 border rounded" disabled>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Student ID</label>
                                <input type="text" value="CS2024-0452" class="w-full p-2 border rounded" disabled>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h2 class="text-2xl font-semibold mb-4">Learning Preferences</h2>
                            <div class="grid md:grid-cols-3 gap-4">
                                <div class="bg-gray-100 p-4 rounded">
                                    <h3 class="font-semibold mb-2">Preferred Learning Style</h3>
                                    <p>Visual Learning</p>
                                </div>
                                <div class="bg-gray-100 p-4 rounded">
                                    <h3 class="font-semibold mb-2">Focus Areas</h3>
                                    <p>Machine Learning, AI</p>
                                </div>
                                <div class="bg-gray-100 p-4 rounded">
                                    <h3 class="font-semibold mb-2">Gender-Based Insights</h3>
                                    <p>Adaptive Learning</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>