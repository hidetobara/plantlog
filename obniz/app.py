import os,sys,string,traceback,random,glob,json,datetime
from flask import Flask, render_template, request, send_from_directory, redirect, jsonify


app = Flask(__name__)

@app.route('/')
def get_index():
    return "Hello world"


if __name__ == "__main__":
    app.run(debug=True,host='0.0.0.0',port=8080)
